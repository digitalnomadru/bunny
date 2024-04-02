<?php
namespace Amqp\Message\Bill;

/**
 * @api {POST} app 1. Request a bill
 * @apiGroup Bill
 * @apiName request
 * @apiDescription
 * Repetitive action published with x-delay by app on new buying trade.
 *
 * Waits for enough payment information to pay particular trade and then publish bill.new.
 *
 * @apiHeader (Actions) chat    Watch messages from client to parse out payment instructions. Publish bill.recipient on complete.
 * @apiHeader (Actions) app     Republish message until enough info was sent by chat. When enough publish bill.new.
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
    "id": 51,
    "state": "REQUEST",
    "trade_id": "5eafdaa7e44b9",
    "created": "2020-05-04 08:00:05"
  }
 *
 */
class BillRequestMessage extends \App\Amqp\Message
{
    public string $state = 'REQUEST';
    public string $trade_id;
    public \DateTime $created;

    public function isValid($value = null)
    {
        $this->errorMessages = [];

        $required = ['id', 'trade_id', 'state'];
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
