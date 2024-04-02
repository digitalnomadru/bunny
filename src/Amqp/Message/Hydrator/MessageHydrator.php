<?php
namespace Amqp\Message\Hydrator;

use Laminas\Hydrator\ObjectPropertyHydrator;
use Laminas\Hydrator\Strategy\DateTimeFormatterStrategy;
use Amqp\Message\Entity\AbstractEntity;

class MessageHydrator extends ObjectPropertyHydrator
{
    public function __construct()
    {
        $this->addStrategy('created', new DateTimeFormatterStrategy('Y-m-d H:i:s'));
    }

    public function extract(object $object) : array
    {
        $array = parent::extract($object);
        foreach ($array as &$value) {
            if ($value instanceof AbstractEntity) {
                $value = $value->extract($value, $object);
            }
        }
        return $array;
    }
}
