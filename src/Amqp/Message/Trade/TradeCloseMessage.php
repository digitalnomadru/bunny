<?php
namespace Amqp\Message\Trade;

use App\Amqp\Message;

/**
 * @api {POST} app,chat 3. Complete trade
 * @apiGroup Trade
 * @apiName close
 * @apiDescription
 * When invoices/bills are paid in full app publish close message.
 *
 * When salepoint got trade completed by client close message is published.
 *
 * @apiHeader (Actions) app     Make sure there are no open invoices/bills.
 * @apiHeader (Actions) chat    Change room topic to reflect CLOSED status. Send thanks to user.
 * @apiHeader (Actions) [lbtc]  For selling trades release coins.
 *
 * @apiHeader (Headers) state
 *
 * @apiParam (Body)     {string{13}}            id              Trade ID.
 * @apiParam (Body)     {string}                state=CLOSED    Always "CLOSED".
 *
 * @apiSuccessExample {json} Example
 *  {
    "id": "5eafdaa7e44b9",
    "state": "CLOSED",
  }
 *
 *
 */
class TradeCloseMessage extends Message
{
    public string $id;
    public string $state = 'CLOSED';
    public string $class = self::class;

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
        if ($value->state != 'CLOSED') {
            $this->errorMessages['invalid_state'] = "State must be CLOSED. Got: {$value->state}";
            return false;
        }

        return true;
    }
}
