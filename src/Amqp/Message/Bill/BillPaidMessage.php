<?php
namespace Amqp\Message\Bill;

use App\Amqp\Message;

/**
 * @api {POST} app  4. Mark paid
 * @apiGroup Bill
 * @apiName paid
 * @apiDescription
 * Published by app when bill has enough assigned transactions to consider it paid.
 *
 * @apiHeader (Actions) [app]     Publish trade.paid if no more unpaid bills left.
 *
 * @apiParam (Body)     {int}                   id              Bill ID. App database key.
 * @apiParam (Body)     {string}                state=PAID
 *
 * @apiSuccessExample {json} Example
 *  {
    "id": 51,
    "state": "PAID",
  }
 *
 */
class BillPaidMessage extends Message
{
    public int $id;
    public string $state = 'PAID';

    public function isValid($value = null)
    {
        $value ??= $this;

        $this->errorMessages = [];

        $required = ['id', 'state'];
        foreach ($required as $prop) {
            if (!isset($value->$prop)) {
                $this->errorMessages["empty_$prop"] = "$prop is not set.";
                return false; // now to avoid method calls on null
            }
        }
        if ($value->state != 'PAID') {
            $this->errorMessages['invalid_state'] = 'State must PAID.';
        }

        return empty($this->errorMessages);
    }
}
