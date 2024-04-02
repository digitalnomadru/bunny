<?php
namespace Amqp\Message;

use App\Amqp\Message;

/**
 * Incoming offers we receive.
 *
 * @deprecated
 */
class OfferMessage extends Message
{
    public string $id;
    public string $refid;

    public Entity\Client $offeror;

    // methods
    public Entity\Tx $input;
    public Entity\Tx $output;

    public bool     $selling;
    public float    $rate;
    public ?int $minFiat;
    public ?int $maxFiat;

    public ?string $title;
    public ?string $terms;
    public ?string $paymentInfo;
    public bool $kycRequired = false;

    public ?array $rawapi;

    public function getHydrator() : Hydrator\MessageHydrator
    {
        if (!isset($this->hydrator)) {
            $hydrator = parent::getHydrator();
            $hydrator->addStrategy('offeror',   new Entity\Client);
            $hydrator->addStrategy('input',     new Entity\Tx);
            $hydrator->addStrategy('output',    new Entity\Tx);
        }
        return $this->hydrator;
    }
}
