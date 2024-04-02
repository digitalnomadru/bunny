<?php
namespace Amqp\Expressive;

use Amqp\Channel;
use Amqp\Expressive\Router\Route;
use Amqp\Expressive\Router\RouteCollector;

use Psr\Container\ContainerInterface;
use Mezzio\MiddlewareFactory;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Laminas\HttpHandlerRunner\RequestHandlerRunner;
use Laminas\Stratigility\MiddlewarePipeInterface;

/**
 * In AMQP mode Application replaces in container:
 *
 *  1. MiddlewareFactory - to disable LazyLoading middleware (process keeps running)
 *  2. Router - to deletegate routing to RabbitMQ
 *  3. Runner - wait for messages and runs Application on each
 *
 */
class Application extends \Mezzio\Application
{
    protected $factory;
    protected $routes;
    protected $pipeline;
    protected Channel $channel;

    /**
     * Impossible to use ReflectionBasedAbstractFactory cause ApplicationPipeline is not actual class.
     */
    static public function factory(ContainerInterface $container): self
    {
        return new static(
            $container->get(MiddlewareFactory::class),
            $container->get('Laminas\\Expressive\\ApplicationPipeline'),
            $container->get(RouteCollector::class),
            $container->get(RequestHandlerRunner::class),
            $container->get(Channel::class)
        );
    }

    /**
     * Copy vars to this scope cause Zend is so private.
     *
     * @param MiddlewareFactory $factory
     * @param MiddlewarePipeInterface $pipeline
     * @param RouteCollector $routes
     * @param RequestHandlerRunner $runner
     */
    public function __construct(
        MiddlewareFactory $factory,
        MiddlewarePipeInterface $pipeline,
        RouteCollector $routes,
        RequestHandlerRunner $runner,
        Channel $channel
    ) {
        // must be called to populate parent privates
        parent::__construct($factory, $pipeline, $routes, $runner);
        $this->factory = $factory;
        $this->pipeline = $pipeline;
        $this->routes = $routes;
        $this->channel = $channel;
    }

    /**
     * Add a route for the route middleware to match.
     *
     * @param null|string|array|callable|MiddlewareInterface|RequestHandlerInterface $middleware
     * @param string $exchange
     *     Middleware or request handler (or service name resolving to one of those types) to associate with route.
     * @param null|string $name The name of the route/queue.
     * @param null|string|array $bindArguments Binding arguments. 'x-match: all' is default. Bind to topic with string value.
     * @param null|string|array $queueOptions
     * @param null|array $flags MQ_* flags for queue definition.
     */
    public function bind($middleware, ?string $exchange = null, ?string $name = null, $bindArguments = null, ?array $queueOptions = null, ?int $flags = null) : Route
    {
        return $this->routes->route(
            $exchange ?? 'amq.direct',
            $this->factory->prepare($middleware),
            $bindArguments ?? [],
            $name ?? '',
            $queueOptions ?? [],
            $flags
        );
    }


    public function run($timeout = null) : void
    {
        $this->channel->run($timeout);
    }
}
