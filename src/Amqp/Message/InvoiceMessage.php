<?php
namespace Amqp\Message;

use Amqp\Message\Entity\Tx;

/**
 * Defined public properties are transmitted in JSON body.
 *
 * @property string             $id         Required. UUID of the related business model object.
 * @property string             $state      Required. Finite state of the object at time of publishing.
 * @property string             $created    Required. Time when agreement is set.
 * @property string             $trade_id   Required.
 * @property int                $satoshi    Required. 100000000'th of BTC in the trade without fees.
 * @property Entity\Tx          $input      Required. What we take.
 * @property Entity\Tx          $output     Required. What we give.
 *
 * @property mixed              $refid      Optional. Trade reference on the platfrom involved.
 *
 * @deprecated
 */
class InvoiceMessage extends \App\Amqp\Message
{
    public string $id;
    public string $state = self::STATE_REQUEST;
    public string $trade_id;
    public Entity\Tx $input;
    public \DateTime $created;

    const STATE_REQUEST  = 'REQUEST';
    const STATE_NEW      = 'NEW';
    const STATE_PAYING   = 'PAYING';
    const STATE_PAID     = 'PAID';
    const STATE_DISPUTE  = 'DISPUTE';
    const STATE_CANCELED = 'CANCELED';
    const STATE_CLOSED   = 'CLOSED';

    public function __construct($body = '', array $headers = [])
    {
        parent::__construct($body, $headers);

        $this->id       ??= uniqid();
        $this->client   ??= new Entity\Client();
        $this->input    ??= new Tx();
        $this->created  ??= new \DateTime();
    }

    public function getHydrator() : Hydrator\MessageHydrator
    {
        if (!isset($this->hydrator)) {
            $hydrator = parent::getHydrator();
            $hydrator->addStrategy('input',     new Entity\Tx);
        }
        return $this->hydrator;
    }

    public function isValid($logger = null)
    {
        if (!parent::isValid($logger)) return false;

        try {
            if (!$this->id)         return false;
            if (!$this->state)      return false;
            if (!$this->input)      return false;
            if (!$this->trade_id)   return false;
            if (!$this->created)    return false;
        } catch (\Error $e) {
            if ($logger) $logger->err("Missing required message property: " . $e->getMessage());
            return false;
        }

        return true;
    }
}
