<?php
namespace Amqp\Message\Entity;

/**
 * Information about sale point
 *
 * @property string     $login      Required. Identified on platform.
 * @property string     $platform   Optional. Website or resource where trade is taking place.
 */
class Agent extends AbstractEntity
{
    public string   $login = 'mock';
    public string   $platform = 'generic';

    public function __toString()
    {
        return $this->login.'@'.$this->platform;
    }

    public function isValid($value = null)
    {
        $value ??= $this;

        $this->errorMessages = [];

        if (!parent::isValid($value)) return false;

        $required = ['login', 'platform'];
        foreach ($required as $prop) {
            if (!isset($value->$prop)) {
                $this->errorMessages["empty_$prop"] = "$prop is not set.";
                return false; // now to avoid method calls on null
            }
        }

        return empty($this->errorMessages);
    }
}
