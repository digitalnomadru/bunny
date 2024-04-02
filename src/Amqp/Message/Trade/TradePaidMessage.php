<?php
namespace Amqp\Message\Trade;

use App\Amqp\Message;

/**
 * @api {POST} chat,lbtc 2. Mark paid
 * @apiGroup Trade
 * @apiName paid
 * @apiDescription
 * Event occurs when trade is marked paid by one of the parties.
 *
 * It does not neccessary mean trade is paid in full or transaction happened at all.
 * Instead, it signals that buyer (we or client) declared payment completed.
 *
 * Published by chat when operator mark payment complete and by salepoints when client mark payment complete.
 *
 * @apiHeader (Actions) chat    Change room topic to reflect PAID status.
 * @apiHeader (Actions) [app]   Hedge buying trades - sell same amount of BTC.
 * @apiHeader (Actions) [lbtc]  Mark buying trades paid with API.
 *
 * @apiHeader (Headers) state
 *
 * @apiParam (Body)     {string{13}}            id              Trade ID.
 * @apiParam (Body)     {string}                state=PAID      Always "PAID".
 *
 * @apiSuccessExample {json} Example
 *  {
    "id": "5eafdaa7e44b9",
    "state": "PAID",
  }
 *
 *
 */
class TradePaidMessage extends Message
{
    public string $id;
    public string $state = 'PAID';
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
        if ($value->state != 'PAID') {
            $this->errorMessages['invalid_state'] = "State must be PAID. Got: {$value->state}";
            return false;
        }

        return true;
    }
}
