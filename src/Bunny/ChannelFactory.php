<?php

namespace Bunny;

use Laminas\Log\Logger;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

class ChannelFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null): Channel
    {
        /** @var AbstractClient $client */
        $client = $container->get(AbstractClient::class);
        $log = $container->get(Logger::class);

        $client->connect();
        $channel = $client->channel();

        // One message at a time transferred. Save network, message may fail.
        $channel->qos(0, 1, true);

        // predefined exchanges in ConfigProvider
        $exchanges = @$container->get('config')['rabbitmq']['exchanges'] ?: [];
        foreach ($exchanges as $args) $channel->exchangeDeclare(...$args);

        return $channel;
    }
}
