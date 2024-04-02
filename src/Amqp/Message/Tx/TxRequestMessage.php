<?php
namespace Amqp\Message\Tx;

use App\Amqp\Message;
use Amqp\Message\Entity\Tx;
use Amqp\Message\Hydrator\MessageHydrator;

/**
 * @api {POST} app 2. Request transaction
 * @apiGroup Tx
 * @apiName request
 * @apiDescription
 * Published to order nostro operator to make a payment.
 *
 * @apiHeader (Actions) [operator]      Make payment manually or automatically and report result.
 *
 * @apiParam (Body)     {string}        state=REQUEST   Always "REQUEST".
 * @apiParam (Body)     {int}           nostro_id       Must pay from this account. App's db ID.
 * @apiParam (Body)     {int}           order_id        App's db ID. Used to publish responses.
 * @apiParam (Body)     {Tx}            output          Entity with "to" key holding payment details.
 * @apiParam (Body)     {float}         [fee]           Expected fee to pay for transfer. Only for reference.
 * @apiParam (Body)     {string{13}}    [trade_id]      Optionally set for reference.
 *
 * @apiSuccessExample {json} Example
 *  {
     "state": "REQUEST",
     "nostro_id": 12,
     "output": {
         "method": "YANDEX",
         "amount": 100.00,
         "currency": "RUB"
     }
     "fee": 0.50,
    }
 *
 * @property float $fee
 * @property string $trade_id
 */
class TxRequestMessage extends Message
{
    public string $state = 'REQUEST';
    public int $nostro_id;
    public int $order_id;
    public Tx $output;

    public function isValid($value = null)
    {
        $value ??= $this;

        $this->errorMessages = [];

        $required = ['state', 'nostro_id', 'order_id', 'output'];
        foreach ($required as $prop) {
            if (!isset($value->$prop)) {
                $this->errorMessages["empty_$prop"] = "$prop is not set.";
                return false; // now to avoid method calls on null
            }
        }
        if ($value->state != 'REQUEST') {
            $this->errorMessages['invalid_state'] = 'State must REQUEST.';
        }
        if (!$this->output->isValid()) {
            $this->errorMessages += $this->output->getMessages();
        }
        if (empty($this->output->to)) {
            $this->errorMessages['empty_to'] = 'No destination info was set.';
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
