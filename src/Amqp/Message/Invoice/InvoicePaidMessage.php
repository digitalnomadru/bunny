<?php
namespace Amqp\Message\Invoice;

use App\Amqp\Message;
use Amqp\Message\BillMessage;

/**
 * @api {POST} app  4. Mark fully paid
 * @apiGroup Invoice
 * @apiName paid
 * @apiDescription
 * Published by app when invoice has enough assigned transactions to consider invoice paid.
 *
 * @apiHeader (Actions) app     Publish trade.close if no more unpaid invoices left.
 *
 * @apiHeader (Headers) state
 *
 * @apiParam (Body)     {int}                   id              Invoice ID. App database key.
 * @apiParam (Body)     {string}                state=PAID
 *
 * @apiSuccessExample {json} Example
 *  {
    "id": 51,
    "state": "PAID",
  }
 *
 */
class InvoicePaidMessage extends Message
{
    public int $id;
    public string $state = BillMessage::STATE_PAID;

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
