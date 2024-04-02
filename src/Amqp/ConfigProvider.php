<?php
namespace Amqp;

use Laminas\ConfigAggregator\ConfigAggregator;
use Amqp\Expressive\Router\RouteCollector;
use Amqp\Expressive\Router\Router;
use Amqp\Application;

class ConfigProvider
{
    public function __invoke() : array
    {
        return (new ConfigAggregator([
            function() {
                return [
                    'dependencies' => [
                        'factories' => [
                            Application::class => [Application::class, 'factory'],
                            Channel::class => [Channel::class, 'factory'],
                            RouteCollector::class => [RouteCollector::class, 'factory'],
                            Message::class => [Message::class, 'factory']
                        ]
                    ],
                    'rabbitmq' => [
                        'host' => 'rabbitmq',
                        'user' => 'guest',
                        'password' => 'guest',
                        // heartbeat = 0 makes rabbit use 100% CPU
                        'heartbeat' => 300,
                        'exchanges' => [
                            ['Health'],
                        ]
                    ]
                ];
            },

            /**
             * If environment says we are running an AMQP consumer, then
             * replace some Container services to listen AMQP instead of HTTP.
             */
            function() {
                // HTTP requests
                if (isset($_ENV['SERVER_NAME'])) return [];
                return [
                    'dependencies' => [
                        'aliases' => [
                            \Mezzio\Application::class => Application::class,
                            \Mezzio\Router\RouteCollector::class => RouteCollector::class,
                            \Mezzio\Router\RouterInterface::class => Router::class,
                        ]
                    ]
                ];
            }
        ]))->getMergedConfig();
    }
}
