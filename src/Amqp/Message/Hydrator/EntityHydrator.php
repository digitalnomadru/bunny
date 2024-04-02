<?php
namespace Amqp\Message\Hydrator;

use Laminas\Hydrator\ObjectPropertyHydrator;

class EntityHydrator extends ObjectPropertyHydrator
{
    protected string $classname;

    public function __construct($classname)
    {
        $this->classname = $classname;
    }


}
