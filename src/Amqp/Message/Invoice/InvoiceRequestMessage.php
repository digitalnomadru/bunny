<?php
namespace Amqp\Message\Invoice;

/**
 * @api {POST} app 1. Request an invoice
 * @apiGroup Invoice
 * @apiName request
 * @apiDescription
 * For selling trades launces process to pick the best nostro to accept fiat.
 *
 * @apiHeader (Actions) app     Republish message until accepting nostro is selected. Publish invoice.new when it's found.
 *
 * @apiHeader (Headers) state
 *
 * @apiParam (Body)     {int}                   [id]            Request ID. App database key.
 * @apiParam (Body)     {string}                state=REQUEST
 * @apiParam (Body)     {string{13}}            trade_id        Trade ID.
 * @apiParam (Body)     {string}                created         Required to expire request.
 *
 * @apiSuccessExample {json} Example
 *  {
    "id": 61,
    "state": "REQUEST",
    "trade_id": "5eafdaa7e44b9",
    "created": "2020-05-04 08:00:05"
  }
 *
 */
class InvoiceRequestMessage extends \App\Amqp\Message
{
    public string $state = 'REQUEST';
    public string $trade_id;
    public \DateTime $created;

    public function isValid($value = null)
    {
        $this->errorMessages = [];

        $required = ['trade_id', 'state'];
        foreach ($required as $prop) {
            if (!isset($this->$prop)) {
                $this->errorMessages["empty_$prop"] = "$prop is not set.";
                return false; // now to avoid method calls on null
            }
        }
        if ($this->created->getTimestamp() > time()) {
            $this->errorMessages['in_future'] = 'Creation time is in future.';
        }

        return empty($this->errorMessages);
    }
}
