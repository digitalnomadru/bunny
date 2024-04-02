<?php
namespace Amqp\Message;

use Amqp\Message;

/**
 * Represent a text message in communication between agent and client.
 *
 * @property string             $id         Required. UUID in our system.
 * @property string             $state      Required. Finite state of the object at time of publishing.
 * @property string             $trade_id   Optional. UUID of the trade context.
 * @property mixed              $refid     Optional. Message reference on the platfrom involved, if any.
 * @property string             $created    Required. Time when agreement is set.
 *
 * @deprecated
 */
class LetterMessage extends Message
{
    const STATE_NEW         = 'new';        // Letter has not been published anywhere yet.
    const STATE_SENT        = 'sent';       // Letter has been handed over to 3rd party medium.
    const STATE_RECEIVED    = 'received';   // We got the letter or 3rd party confirmed receipt.

    public string           $id;
    public bool             $incoming;
    public string           $trade_id;
    public string $state    = self::STATE_NEW;
    public string  $body        = '';
    public ?string $file        = null;
    public string  $refid      = '';

    protected array $PROPS_TO_HEADERS = ['incoming', 'trade_id', 'state'];


    public function __construct($body = '', array $headers = [])
    {
        $this->id = uniqid();
        $this->agent = new Entity\Agent();
        $this->client = new Entity\Client;

        parent::__construct($body, $headers);
    }

    public function getHydrator() : Hydrator\MessageHydrator
    {
        if (!isset($this->hydrator)) {
            $hydrator = parent::getHydrator();
            $hydrator->addStrategy('agent',     new Entity\Agent);
            $hydrator->addStrategy('client',    new Entity\Client);
        }
        return $this->hydrator;
    }
}
