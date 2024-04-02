<?php
namespace Amqp\Message\Trade;

use App\Amqp\Message;

/**
 * @api {POST} chat,lbtc 4. Cancel trade
 * @apiGroup Trade
 * @apiName cancel
 * @apiDescription
 * Published by salepoint when client cancels selling trade.
 *
 * Published by chat when operator cancels buying trade.
 *
 * @apiHeader (Actions) app     Cancel all invoices/bills.
 * @apiHeader (Actions) chat    Change room topic to reflect CANCELED status.
 * @apiHeader (Actions) [lbtc]  For buying trades call API to cancel.
 *
 * @apiHeader (Headers) state
 *
 * @apiParam (Body)     {string{13}}            id              Trade ID.
 * @apiParam (Body)     {string}                state=CANCELED  Always "CANCELED".
 *
 * @apiSuccessExample {json} Example
 *  {
    "id": "5eafdaa7e44b9",
    "state": "CANCELED",
  }
 *
 *
 */
class TradeCancelMessage extends Message
{
    public string $id;
    public string $state = 'CANCELED';

    public function isValid($value = null)
    {
        $value ??= $this;

        $required = ['id', 'state'];
        foreach ($required as $prop) {
            if (!isset($value->$prop)) {
                $this->errorMessages["empty_$prop"] = "$prop is not set.";
                return false; // now to avoid method calls on null
            }
        }
        if ($value->state != 'CANCELED') {
            $this->errorMessages['invalid_state'] = "State must be CANCELED. Got: {$value->state}";
            return false;
        }

        return true;
    }
}
