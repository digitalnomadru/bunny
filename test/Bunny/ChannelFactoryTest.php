<?php

namespace Bunny;

use Laminas\Log\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Interop\Container\ContainerInterface;

class ChannelFactoryTest extends TestCase
{
    /** @var ContainerInterface|MockObject */
    private $container;


    protected function setUp(): void
    {
        $this->container = $this->createMock(ContainerInterface::class);
    }

    public function testInvoke(): void
    {
        $client = $this->createMock(AbstractClient::class);
        $logger = $this->createMock(Logger::class);

        $client = new Client(['host' => 'rabbitmq']);

        $this->container
            ->method('get')
            ->withConsecutive([AbstractClient::class], [Logger::class])
            ->willReturnOnConsecutiveCalls($client, $logger);

        $factory = new ChannelFactory();

        $channel = $factory($this->container, Channel::class, ['channelId' => 1]);

        $this->assertInstanceOf(Channel::class, $channel);
    }
}
