<?php
namespace Amqp\Message\Bill;

use Amqp\Message\Entity\Tx;
use Amqp\Message\Hydrator\MessageHydrator;

/**
 * @api {POST} app 3. Create a bill
 * @apiGroup Bill
 * @apiName new
 * @apiDescription
 * Bill in system represents usual accounting bill - outgoing payment needed to receive coins.
 *
 * When payment details are collected app publish bill as request to pay the trade.
 *
 * @apiHeader (Actions) [app]     Send payment instructions to assigned operator, if any.
 *
 * @apiHeader (Headers) state
 *
 * @apiParam (Body)     {int}                   id              Request ID. App database key.
 * @apiParam (Body)     {string}                state=NEW
 * @apiParam (Body)     {string{13}}            trade_id        Trade ID.
 * @apiParam (Body)     {Tx}                    output          Entity with "to" key holding payment details.
 * @apiParam (Body)     {string}                created         MySQL datetime.
 *
 * @apiSuccessExample {json} Example
 *  {
    "id": 51,
    "state": "REQUEST",
    "trade_id": "5eafdaa7e44b9",
    "output": {
      "to": {
        "login": "0123456789"
      }
    },
    "created": "2020-05-04 08:00:05"
  }
 *
 */
class BillNewMessage extends \App\Amqp\Message
{
    public int $id;
    public string $state = 'NEW';
    public string $trade_id;
    public Tx $output;
    public \DateTime $created;

    public function isValid($value = null)
    {
        $value ??= $this;

        $this->errorMessages = [];

        $required = ['id', 'state', 'trade_id', 'output', 'created'];
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

        $childs = ['output'];
        foreach ($childs as $prop) {
            $child = $this->$prop;
            if (!$child->isValid()) {
                $this->errorMessages += $child->getMessages();
                return false;
            }
        }

        if (empty($value->output->to) || !is_array($value->output->to)) {
            $this->errorMessages['empty_to'] = 'output.to must be set to array. Got '.gettype($value->output->to);
        }

        return empty($this->errorMessages);
    }

    public function getHydrator() : MessageHydrator
    {
        if (!isset($this->hydrator)) {
            $hydrator = parent::getHydrator();
            $hydrator->addStrategy('output',    new Tx());
        }
        return $this->hydrator;
    }
}
