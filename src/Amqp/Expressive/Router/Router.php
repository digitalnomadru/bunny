<?php
namespace Amqp\Expressive\Router;

use Psr\Http\Message\ServerRequestInterface as Request;
use X\Exception\NotImplementedException;
use X\Traits\Channel;
use Mezzio\Router\Route;
use Mezzio\Router\RouteResult;
use Mezzio\Router\RouterInterface;

/**
 * Routing is delegated to RabbitMQ.
 *
 * There is one queue per route bound  to given exchange with given headers.
 * Router registers callbacks in Bunny, one consumer per route/queue.
 * When message is received it is visible from consumerTag which route message delivered.
 *
 * Router launches ApplicationPipeline which contains DispatchMiddleware that will run handlers for matched router.
 *
 */
class Router implements RouterInterface
{
    use Channel;

    protected $routes = [];

    public function addRoute(Route $route) : void
    {
        // Consume from new queue bound with given route
        $ch = $this->getChannel();
        $route->name = $queue = $ch->queueDeclare($route->name, $route->getOptions(), $route->flags);
        $ch->bind($queue, $route->exchange, $route->arguments, $route->rkey);
        $route->tag = $tag = $ch->consume($queue);
        $this->routes[$tag] = $route;
    }

    public function match(Request $request) : RouteResult
    {
        $tag = $request->getAttribute('consumerTag');
        if (!$tag) throw new \InvalidArgumentException('Request is missing consumerTag');

        if ($route = @$this->routes[$tag]) {
            return RouteResult::fromRoute($route);
        } else {
            return RouteResult::fromRouteFailure(null);
        }
    }

    public function generateUri(string $name, array $substitutions = [], array $options = []) : string
    {
        throw new NotImplementedException('Cannot generate URI in AMQP router.');
    }
}
