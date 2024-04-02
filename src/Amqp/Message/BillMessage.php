<?php
namespace Amqp\Message;

use Amqp\Message\Entity\Tx;

/**
 * @deprecated
 */
class BillMessage extends \App\Amqp\Message
{
    public string $state = self::STATE_REQUEST;
    public string $trade_id;
    public Tx $output;
    public $recipient;
    public \DateTime $created;

    const STATE_REQUEST  = 'REQUEST';
    const STATE_NEW      = 'NEW';
    const STATE_PAID     = 'PAID';
    const STATE_DONE     = 'DONE';
    const STATE_CANCELED = 'CANCELED';

    public function getHydrator() : Hydrator\MessageHydrator
    {
        if (!isset($this->hydrator)) {
            $hydrator = parent::getHydrator();
            $hydrator->addStrategy('output',    new Entity\Tx);
        }
        return $this->hydrator;
    }
}
