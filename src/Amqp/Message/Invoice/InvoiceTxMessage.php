<?php
namespace Amqp\Message\Invoice;

/**
 * @api {POST} app 3. Assign a tx
 * @apiGroup Invoice
 * @apiName tx
 * @apiDescription
 * Flag existing incoming transaction as being received for speific invoice. Tx can't have more than one invoice.
 *
 * Published by app on tx.new if there are no doubts for which invoice this incoming tx is.
 *
 * Can be manually published by human support to assign unmatched txs. That is why its own endpoint.
 *
 * @apiHeader (Actions) [app]     Publish invoice.paid if amount of tx(s) is enough.
 *
 * @apiParam (Body)     {int}   id           Invoice ID. App database key.
 * @apiParam (Body)     {int}   tx_id        Tx ID. App database key.
 *
 * @apiSuccessExample {json} Example
 *  {
    "id": 51,
    "tx_id": 1001
  }
 *
 */
class InvoiceTxMessage extends \App\Amqp\Message
{
    public int $id;
    public int $tx_id;

    public function isValid($value = null)
    {
        $value ??= $this;

        $this->errorMessages = [];

        $required = ['id', 'tx_id'];
        foreach ($required as $prop) {
            if (!isset($value->$prop)) {
                $this->errorMessages["empty_$prop"] = "$prop is not set.";
                return false; // now to avoid method calls on null
            }
        }

        return empty($this->errorMessages);
    }
}
