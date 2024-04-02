<?php
namespace Amqp\Expressive\Router;

use Amqp\Message;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @property string $exchange
 * @property MiddlewareInterface $middleware
 * @property array $arguments
 */
class Route extends \Mezzio\Router\Route
{
    public $flags;
    public $arguments = [];
    public $rkey = '';
    public $tag = null;

    public function __construct(
        string $exchange,
        MiddlewareInterface $middleware,
        $arguments = [],
        string $name = '',
        ?array $options = [],
        ?int $flags = 0
    ) {
        $this->exchange   = $exchange;
        $this->middleware = $middleware;

        if (is_string($arguments)) {
            $this->rkey = $arguments;
            $arguments = [];
        }
        else if (is_array($arguments)) {
            // these headers always travel
            $arguments['version'] = Message::VERSION;
            $arguments['x-match'] = @$arguments['x-match'] ?: 'all';
        }
        else throw new \InvalidArgumentException($arguments);

        $this->arguments = $arguments;
        $this->setOptions($options);
        $this->name = $name ?? '';
        $this->flags = $flags;

        // copy up stack
        parent::__construct(
            '/'.$this->name,
            $this->middleware,
            self::HTTP_METHOD_ANY,
            $this->name
        );
    }

    /**
     * {@inheritdoc}
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
        return $this->middleware->process($request, $handler);
    }
}
