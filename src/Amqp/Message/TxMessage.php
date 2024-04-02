<?php
namespace Amqp\Message;

use App\Amqp\Message;

/**
 * @deprecated
 */
class TxMessage extends Message
{
    const STATE_REQUEST     = 'REQUEST';
    const STATE_NEW         = 'NEW';
    const STATE_BLOCKED     = 'BLOCKED';
    const STATE_CANCELED    = 'CANCELED';

    const TYPE_ORDER = 'ORDER';
    const TYPE_CASH = 'CASH';
    const TYPE_SPEND = 'SPEND';

    public $id;
    public $state = self::STATE_NEW;
    public $type;
    public $method;
    public $is_out;
    public $amount;
    public $fee;
    public $refid;
    public $sender;
    public $balance; // new balance if known
    public $trade_id; // to close invoices for a particular trade (optional)
    public $nostro_id;
    public $order_id; // out txs only
    public $extra; // will be written to db as-is

    public function isValid($logger = null)
    {
        if (!parent::isValid($logger)) return false;

        // enough to find all the other data
        if (isset($this->trade_id)) return true;

        try {
            if (!$this->amount || !is_numeric($this->amount) || $this->amount <= 0) return false;
            if (!$this->nostro_id && !$this->trade_id) return false;
        } catch (\Error $e) {
            if ($logger) $logger->err("Missing required message property: " . $e->getMessage());
            return false;
        }

        return true;
    }
}
