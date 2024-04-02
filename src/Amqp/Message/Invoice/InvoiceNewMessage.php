<?php
namespace Amqp\Message\Invoice;

use Amqp\Message\Entity\Tx;
use Amqp\Message\Hydrator\MessageHydrator;

/**
 * @api {POST} app 2. Create an invoice
 * @apiGroup Invoice
 * @apiName new
 * @apiDescription
 * Accepting nostro has been selected. Build invoice and publish.
 *
 * @apiHeader (Actions) chat     Send payment instructions to client.
 *
 * @apiHeader (Headers) state
 *
 * @apiParam (Body)     {int}                   id              Request ID. App database key.
 * @apiParam (Body)     {string}                state=NEW
 * @apiParam (Body)     {string{13}}            trade_id        Trade ID.
 * @apiParam (Body)     {int}                   [nostro_id]     Account id for reference.
 * @apiParam (Body)     {Entity/Tx}             input           Entity with "to" key holding payment details for client.
 * @apiParam (Body)     {string}                created         MySQL datetime.
 *
 * @apiSuccessExample {json} Example
 *  {
    "id": 51,
    "state": "REQUEST",
    "trade_id": "5eafdaa7e44b9",
    "input": {
      "amount": "100",
      "currency": "RUB",
      "method": "YANDEXMONEY",
      "to": {
        "login": "0123456", "name": "John Doe"
      }
    },
    "created": "2020-05-04 08:00:05"
  }
 *
 */
class InvoiceNewMessage extends \App\Amqp\Message
{
    public int $id;
    public string $state = 'NEW';
    public string $trade_id;
    public Tx $input;
    public \DateTime $created;

    public function isValid($value = null)
    {
        $value ??= $this;

        $this->errorMessages = [];

        $required = ['id', 'state', 'trade_id', 'input', 'created'];
        foreach ($required as $prop) {
            if (!isset($value->$prop)) {
                $this->errorMessages["empty_$prop"] = "$prop is not set.";
                return false; // now to avoid method calls on null
            }
        }
        if ($value->state != 'NEW') {
            $this->errorMessages['invalid_state'] = 'State must NEW.';
        }
        if ($value->created->getTimestamp() > time()) {
            $this->errorMessages['in_future'] = 'Creation time is in future.';
        }

        $childs = ['input'];
        foreach ($childs as $prop) {
            $child = $this->$prop;
            if (!$child->isValid()) {
                $this->errorMessages += $child->getMessages();
                return false;
            }
        }

        if (empty($value->input->to) || !is_array($value->input->to)) {
            $this->errorMessages['empty_to'] = 'input.to must be set to array. Got '.gettype($value->output->to);
        }

        return empty($this->errorMessages);
    }

    public function getHydrator() : MessageHydrator
    {
        if (!isset($this->hydrator)) {
            $hydrator = parent::getHydrator();
            $hydrator->addStrategy('input',    new Tx());
        }
        return $this->hydrator;
    }
}
