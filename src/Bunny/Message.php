<?php
namespace Bunny;

class Message
{
    public function __construct(
        public string   $content     = '',
        public array    $headers     = [],
        public string   $exchange    = '',
        public string   $routingKey  = '',
        public string   $consumerTag = '',
        public ?int     $deliveryTag = null,
        public bool     $redelivered = false
    )
    {}

    /**
     * Returns header or default value.
     */
    public function getHeader(string $name, mixed $default = null): mixed
    {
        if (isset($this->headers[$name])) {
            return $this->headers[$name];
        } else {
            return $default;
        }
    }

    /**
     * Returns TRUE if message has given header.
     */
    public function hasHeader(string $name): bool
    {
        return isset($this->headers[$name]);
    }

}
