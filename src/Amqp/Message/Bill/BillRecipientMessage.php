<?php
namespace Amqp\Message\Bill;

/**
 * @api {POST} app,chat 2. Set payment details
 * @apiGroup Bill
 * @apiName recipient
 * @apiDescription
 * Only for buying trades.
 *
 * Published by chat when client communicates payment details in trade chat.
 *
 * Published by app if payment details are known at trade creation time.
 *
 * @apiHeader (Actions) app     Publish bill.new with payment details.
 *
 * @apiParam (Body)     {int}       id          Request ID. App database key.
 * @apiParam (Body)     {array}     recipient   Payment details for this specific method.
 *
 * @apiSuccessExample {json} Example
 *  {
    "id": 51,
    "recipient": {"login": "1234567890"}
  }
 *
 */
class BillRecipientMessage extends \App\Amqp\Message
{
    public int $id;
    public array $recipient;

    public function isValid($value = null)
    {
        $this->errorMessages = [];

        $required = ['id', 'recipient'];
        foreach ($required as $prop) {
            if (!isset($this->$prop)) {
                $this->errorMessages["empty_$prop"] = "$prop is not set.";
                return false; // now to avoid method calls on null
            }
        }

        return empty($this->errorMessages);
    }
}
