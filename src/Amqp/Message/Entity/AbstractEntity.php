<?php
namespace Amqp\Message\Entity;

use Laminas\Validator\ValidatorInterface;
use Laminas\Hydrator\Strategy\StrategyInterface;
use Laminas\Hydrator\ObjectPropertyHydrator;
use Laminas\Hydrator\HydratorInterface;
use X\Traits\ArrayAccessTrait;

abstract class AbstractEntity implements ValidatorInterface, StrategyInterface, \ArrayAccess
{
    use ArrayAccessTrait;

    protected $errorMessages = [];

    public function __construct(array $props = [])
    {
        foreach ($props as $k => $v) $this->$k = $v;
    }

    public function __toString()
    {
        return $this->toJson();
    }

    public function isValid($value = null)
    {
        $value ??= $this;

        return true;
    }

    public function getMessages()
    {
        return $this->errorMessages;
    }

    /**
     * Overload in childs to implement strategies of entities hydration.
     */
    public function getHydrator() : HydratorInterface
    {
        return new ObjectPropertyHydrator();
    }
    public function extract($self, ?object $parent = null)
    {
        $ar = $this->getHydrator()->extract($self);
        return $ar;
    }
    public function hydrate($array, ?array $data) : self
    {
        if ($array instanceof StrategyInterface) $array = $this->extract($array);
        $this->getHydrator()->hydrate($array, $this);
        return $this;
    }

    public function toJson()
    {
        return json_encode_pretty($this->extract($this));
    }
}
