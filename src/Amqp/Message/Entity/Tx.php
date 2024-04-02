<?php
namespace Amqp\Message\Entity;

/**
 * @apiDefine Tx Tx
 * @apiParam (Tx)       {string}                amount          Can be BTC, so precision is 8.
 * @apiParam (Tx)       {string}                currency        ISO-3
 * @apiParam (Tx)       {string}                method          One of supported money flow methods.
 * @apiParam (Tx)       {string}                [account]       Public identifier of associated account. Usually can receive using this value. E.g. wallet number, email or card number.
 * @apiParam (Tx)       {string}                [name]          Physical person or company name.
 * @apiParam (Tx)       {string}                [country]       ISO-2
 */
class Tx extends AbstractEntity
{
    public string $state    = self::STATE_REQUEST;
    const STATE_REQUEST     = 'REQUEST';            // When there is a plan to do a transaction.
    const STATE_SENT        = 'SENT';               // When sending party confirmed it was sent.
    const STATE_RECEIVED    = 'RECEIVED';           // When receiving institution (bank) confirm receipt.
    const STATE_FAILED      = 'FAILED';

    public string $method;
    public string $currency;
    public float  $amount;

    public $from  = null;
    public $to    = null;

    public array $extra = [];

    static public function build(string $desc = '') : self
    {
        $list = explode(' ', $desc);
        return new self([
            'amount' => $list[0],
            'currency' => $list[1],
            'method' => $list[2]
        ]);
    }

    public function isValid($value = null)
    {
        $value ??= $this;

        $this->errorMessages = [];

        if (!parent::isValid($value)) return false;

        $required = ['method', 'currency', 'amount'];
        foreach ($required as $prop) {
            if (!isset($value->$prop)) {
                $this->errorMessages["empty_$prop"] = "$prop is not set.";
                return false; // now to avoid method calls on null
            }
        }

        return empty($this->errorMessages);
    }

    public function isComplete()
    {
        return $this->state == self::STATE_RECEIVED;
    }
}
