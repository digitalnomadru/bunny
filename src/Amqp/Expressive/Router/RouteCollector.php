<?php
namespace Amqp\Expressive\Router;

use Psr\Http\Server\MiddlewareInterface;
use Interop\Container\ContainerInterface;
use Mezzio\Router\RouterInterface;

/**
 * On adding of a route, binds App's queue using config given in Route.
 */
class RouteCollector extends \Mezzio\Router\RouteCollector
{
    static public function factory(ContainerInterface $container)
    {
        return new static($container->get(RouterInterface::class));
    }

    /**
     * Add a route for the route middleware to match.
     *
     * Ignores HTTP routes, starting with / , cause
     * if this class is in container then we are in AMQP mode.
     *
     * @param string $exchange Exchange name to bind.
     * @param MiddlewareInterface $middleware Pipeline to run on match.
     * @param array $headers Key value pair to filter messages. Empty array to accept all.
     * @param null|string $name Value of AMQP x-match header. 'all' (default) or 'any'
     */
    public function route(
        string $exchange,
        MiddlewareInterface $middleware,
        $headers = null,
        ?string $name = null,
        ?array $options = null,
        ?int $flags = 0
    ) : \Mezzio\Router\Route
    {
        if (@$exchange[0] == '/') {
            return new Route('dummyreturn', $middleware);
        }

        $this->routes[] = $route = new Route($exchange, $middleware, $headers, $name ?? '', $options, $flags);
        $route->rkey = is_string($headers) ? $headers : '';

        $this->router->addRoute($route);

        return $route;
    }
}
