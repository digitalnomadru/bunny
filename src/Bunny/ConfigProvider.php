<?php
namespace Bunny;

use Amqp\Channel;
use Amqp\Message;

class ConfigProvider
{
    public function __invoke() : array
    {
        return [
            'dependencies' => [
                'factories' => [
                    Channel::class => [Channel::class, 'factory'],
                    Message::class => [Message::class, 'factory']
                ],
                'aliases' => [
                    AbstractClient::class => Client::class
                ]
            ],
            'rabbitmq' => [
                'host' => 'rabbitmq',
                'user' => 'guest',
                'password' => 'guest',
                'heartbeat' => 300,     // heartbeat = 0 makes rabbit use 100% CPU
                'exchanges' => []
            ]
        ];
    }
}
