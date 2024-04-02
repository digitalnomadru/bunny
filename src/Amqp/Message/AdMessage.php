<?php
namespace Amqp\Message;

/**
 * Outgoing offers we make.
 * @deprecated
 */
class AdMessage extends \Amqp\Message
{
    public string $id;

    const WAY_ASK = 'ask';
    const WAY_BID = 'bid';
    public string $way;
    public string $symbol;
    public string $method;
    public ?string $country = null;
    public float    $rate;
    public ?int $minFiat;
    public ?int $maxFiat;

    public ?string $title;
    public ?string $terms;
    public ?string $paymentInfo;
}
