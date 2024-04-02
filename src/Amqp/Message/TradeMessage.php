<?php
namespace Amqp\Message;

use Amqp\Message\Entity\Tx;

/**
 * @deprecated Use specific classes instead.
 *
 * Defined public properties are transmitted in JSON body.
 *
 * @property string             $id         Required. UUID of the related business model object.
 * @property string             $state      Required. Finite state of the object at time of publishing.
 * @property string             $created    Required. Time when agreement is set.
 *
 * @property Entity\Agent       $agent      Required. Website/Account resource dealing with customer.
 * @property Entity\Client      $client     Required. Information about the counterparty.
 *
 * @property bool               $selling    Required. True if we receive fiat.
 * @property int                $satoshi    Required. 100000000'th of BTC in the trade without fees.
 * @property float              $rate       Required. Agreed rate for 1 BTC in fiat's currency.
 *
 * @property Entity\Tx          $input      Required. What we take.
 * @property Entity\Tx          $output     Required. What we give.
 *
 * @property mixed              $refid      Optional. Trade reference on the platfrom involved.
 */
class TradeMessage extends \App\Amqp\Message
{
    const STATE_NEW      = 'NEW';
    const STATE_PAYING   = 'PAYING';
    const STATE_PAID     = 'PAID';
    const STATE_DISPUTE  = 'DISPUTE';
    const STATE_CANCELED = 'CANCELED';
    const STATE_CLOSED   = 'CLOSED';

    public string $id;
    public string $state;
    public \DateTime $created;

    public Entity\Agent  $agent;
    public Entity\Client $client;
    public Entity\Tx $input;
    public Entity\Tx $output;

    public bool $selling;
    /**
     *
     * @deprecated
     */
    public int $satoshi;
    public float $rate;
    public string $refid = '';

    /**
     * @deprecated
     */
    public array $extra = [];

    public function __construct($body = '', array $headers = [])
    {
        parent::__construct($body, $headers);

        $this->id       ??= uniqid();
        $this->state    ??= self::STATE_NEW;
        $this->agent    ??= new Entity\Agent();
        $this->client   ??= new Entity\Client();
        $this->created  ??= new \DateTime();
        $this->input    ??= new Tx();
        $this->output   ??= new Tx();

        $this->PROPS_TO_HEADERS[] = 'selling';
    }

    public function isValid($logger = null)
    {
        if (!parent::isValid($logger)) return false;

        try {
            if (!$this->id)         return false;
            if (!$this->state)      return false;
        } catch (\Error $e) {
            if ($logger) $logger->err("Missing required message property: " . $e->getMessage());
            return false;
        }

        return true;
    }

    public function getHydrator() : Hydrator\MessageHydrator
    {
        if (!isset($this->hydrator)) {
            $hydrator = parent::getHydrator();
            $hydrator->addStrategy('agent',     new Entity\Agent);
            $hydrator->addStrategy('client',    new Entity\Client);
            $hydrator->addStrategy('input',     new Entity\Tx);
            $hydrator->addStrategy('output',    new Entity\Tx);
        }
        return $this->hydrator;
    }
}
