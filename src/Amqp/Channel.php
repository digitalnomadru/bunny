<?php
namespace Amqp;

use X\Exception\NotImplementedException;
use X\Traits\Logger;
use Psr\Container\ContainerInterface;
use Bunny\Exception\ClientException;
use Mezzio\Application;
use X\Traits\Container;
use Psr\Http\Message\ResponseInterface;
use X\Handler\AbstractHandler;
use Amqp\Response\ResponseRepublish;
use Laminas\Diactoros\Response\TextResponse;
use Amqp\Response\ResponseReject;

// Used to pass flags as a single parameter
define('MQ_DURABLE',    1);
define('MQ_EXCLUSIVE',  2);
define('MQ_AUTODELETE', 4);
define('MQ_PASSIVE',    8);
define('MQ_NOWAIT',    16);
define('MQ_INTERNAL',  32);
define('MQ_IFUNUSED',  64);
define('MQ_IFEMPTY',  128);
define('MQ_NOACK',    256);
define('MQ_NOLOCAL',  512);

/**
 * Interface to AMQP channel to run commands.
 * Supports sensible to Apps features and follows few conventions to structure RabbitMQ.
 *
 * @property \Bunny\Channel $bunny Underlying AMQP library
 * @property \Bunny\Client  $client
 */
class Channel
{
    static public array $defaultQueueArguments = [
        'x-max-priority' => Message::PRIORITY_EMERG
    ];
    static public int $defaultQueueFlags = 0;

    public $bunny;
    public $client;
    private $id;

    /**
     * Impossible to use ReflectionBasedAbstractFactory cause ApplicationPipeline is not actual class.
     * Thus we can't use it as a type hint and factory was redefined.
     */
    public function __construct() {}

    /**
     * One channel is opened for each php process.
     * In this channel, App creates queues and consumes from them.
     * Definition of routing defines what queues to define and handlers to run.
     * Bridge to AMQP is consumerTag, thus one consumer per queue per process.
     *
     * Multithreading is achieved by registering multiple consumers on same queues.
     * Every process will have own channel and consumers.
     */
    static public function factory(ContainerInterface $container) : self
    {
        $log = $container->get(\Laminas\Log\Logger::class);
        $self = new self();
        $self->client = $container->build(Client::class);
        try {
            $self->client->connect();
        } catch (ClientException $e) {
            if (str_contains($e->getMessage(), 'getaddrinfo for rabbitmq failed: Name or service not known')) {
                $log->notice('Failed to resolve "rabbitmq".');
                sleep(1); // docker can't
                exit(504);
            }
            else if (str_contains($e->getMessage(), 'Connection refused')) {
                $log->notice($e->getMessage());
                sleep(1); // docker can't
                exit(504);
            }
            else throw $e;
        }

        try {
            $self->bunny = $self->client->channel();
            $self->id = $self->bunny->id;
        }
        catch (ClientException $e) {
            if (strpos($e->getMessage(), 'Connection refused')) {
                $log->debug($e->getMessage()); // no stacktrace
            }
            else throw $e;
        }

        // One message at a time transferred. Save network, message may fail.
        $self->bunny->qos(0, 1, true);

        $exchanges = @$container->get('config')['rabbitmq']['exchanges'] ?: [];
        foreach ($exchanges as $args) $self->exchangeDeclare(...$args);

        return $self;
    }

    /**
     * Invoked by Bunny as first call after message is received.
     * Runs ApplicationPipeline, including middleware.
     * Router will match route to run by consumerTag.
     *
     * @param \Bunny\Message $bunny
     * @param \Bunny\Channel $channel Useful in tests for mocks.
     */
    public function __invoke(\Bunny\Message $bunny, ?\Bunny\Channel $channel = null) : ResponseInterface
    {
        $channel = $channel ?? $this->bunny;

        try {
            $request = $this->getContainer()->build(Message::class, ['bunnyMessage' => $bunny]);
        } catch (\Throwable $e) {
            $this->getLogger()->err($e);
            $channel->reject($bunny, false); // throw out garbage
            return new TextResponse('Message failed to build.', 500);
        }

        $app = $this->getContainer()->get(Application::class);
        $response = $app->handle($request);

        $channel->lastRequest  = $request;
        $channel->lastResponse = $response;

        if ($response instanceof ResponseRepublish) {
            $response($this);
        }

        if ($response instanceof ResponseReject) {
            if ($text = (string) $response->getBody()) {
                $this->getLogger()->notice($text);
            }
        }

        // emit response AMQP understands
        $code = $response->getStatusCode();
        if (($code == 100) || ($code >= 500 && $code <= 599)) {
            $channel->nack($bunny);
        }
        else if ($code >= 200 && $code <= 299) {
            $channel->ack($bunny);
        }
        else if ($code >= 400 && $code <= 499) {
            $channel->nack($bunny, false, false);
        }
        else {
            $channel->ack($bunny);
        }
        return $response;
    }

    /**
     * Publish $message to $exchange.
     *
     * @param Message $message
     * @param string $exchange
     * @param string $rkey
     * @return self
     */
    public function publish(Message $message, string $exchange = 'delay.headers', string $rkey = '') : self
    {
        $headers = array_merge([
            'class' => array_reverse(explode('\\', get_class($message)))[0]
        ], $message->getArrayCopy()['*headers']);

        $this->client->publish(
            $this->id,
            (string) $message,
            $headers,
            $exchange,
            $rkey
        );
        return $this;
    }

    public function direct(string $handlerServiceName, ?Message $message = null)
    {
        $s = $this->getContainer()->get($handlerServiceName);
        if (!$s instanceof AbstractHandler) {
            throw new \InvalidArgumentException("$handlerServiceName service must be AbstractHandler");
        }
        $message ??= new Message;

        return $this->publish($message, '', $s::$queueName);
    }

    /**
     * RPC call: send a message and wait for reply.
     *
     * @param Call $message
     * @throws NotImplementedException
     * @return Message
     */
    public function call(Message $message, string $exchange, string $rkey = '', int $timeout = 10) : ?Message
    {
        // register replies consumer once per channel (so direct reply-to knows our consumer tag)
        $reply = null;
        if (!isset($this->replyConsumer)) {
            $this->replyConsumer =
            function(\Bunny\Message $message, \Bunny\Channel $channel) use (&$reply) {
                $reply = $this->getContainer()->build(Message::class, ['bunnyMessage' => $message]);
                $this->client->stop(); // stop receiving on first reply
            };
            $this->bunny->consume($this->replyConsumer, 'amq.rabbitmq.reply-to', '', false, true);
        }

        $message['reply-to'] = 'amq.rabbitmq.reply-to';
        $this->publish($message, $exchange, $rkey);

        $this->client->run($timeout);

        if ($reply) return $reply;
        else return null;
    }

    /**
     * Exchanges in App works exactly as topics, although its body is usually a model.
     * Models broadcast itselves when something happens or request to happen.
     * Model fields such as 'status' used in routing are in messages headers.
     *
     * Every App's exchange is 'headers' type with delayed messages enabled.
     *
     * @param string $name Can't start with 'amq.'
     * @param array $arguments Routing type is set in 'x-delayed-type' value.
     * @param int $flags Bitwise MQ_* flags. MQ_DURABLE is enforced.
     */
    public function exchangeDeclare(string $name, array $arguments = [], int $flags = MQ_DURABLE, string $type = 'x-delayed-message')
    {
        if ($type == 'x-delayed-message' && !@$arguments['x-delayed-type']) {
            $arguments['x-delayed-type'] = 'headers';
        }

        // until there is usage for not durable exchanges
        $flags |= MQ_DURABLE;

        $this->client->exchangeDeclare(
            $this->id,
            $name,
            $type,
            $flags & MQ_PASSIVE,
            $flags & MQ_DURABLE,
            $flags & MQ_AUTODELETE,
            $flags & MQ_INTERNAL,
            $flags & MQ_NOWAIT,
            $arguments
        );
    }

    /**
     * Delete an exchange.
     *
     * @param string $exchange
     * @param int $flags (MQ_IFUNUSED, MQ_NOWAIT)
     */
    public function exchangeDelete(string $exchange, int $flags = 0)
    {
        $this->client->exchangeDelete($this->id, $exchange, $flags & MQ_IFUNUSED, $flags & MQ_NOWAIT);
    }

    /**
     * In App queues are connected to each Handler class or their combination.
     * When queue receive a message, bunny calls back this class.
     * Which then run App pipeline with routing match to queue's Handler(s).
     *
     * Queues are non-excl. by default so App can restart and continue without loss.
     *
     * @param string $name
     * @param array $arguments
     * @throws \RuntimeException
     * @return string
     */
    public function queueDeclare(?string $name = '', array $arguments = [], ?int $flags = null) : string
    {
        $flags ??= static::$defaultQueueFlags;
        $flags |= MQ_DURABLE; // there is no usage case for not durable

        $ok = $this->client->queueDeclare(
            $this->id,
            $name ?? '',
            $flags & MQ_PASSIVE,
            $flags & MQ_DURABLE,
            $flags & MQ_EXCLUSIVE,
            $flags & MQ_AUTODELETE,
            $flags & MQ_NOWAIT,
            array_merge(static::$defaultQueueArguments, $arguments)
        );
        return $ok->queue;
    }

    /**
     * Delete all messages.
     *
     * @param string $name
     */
    public function queuePurge(string $name)
    {
        $this->client->queuePurge($this->id, $name);
    }

    /**
     * Delete a queue.
     *
     * @param string $queue
     * @param int $flags
     */
    public function queueDelete(string $queue, int $flags = 0)
    {
        $this->client->queueDelete($this->id, $queue, $flags & MQ_IFUNUSED, $flags & MQ_IFEMPTY, $flags & MQ_NOWAIT);
    }

    /**
     * Bind App's queue to an Exchange.
     *
     * @param string $exchange Name of existing exchange.
     * @param array $arguments Headers to filter or other queue arguments.
     * @param string $name Queue name other than default.
     */
    public function bind(string $queue, string $exchange, array $arguments = [], string $rkey = '', ?int $flags = null) : void
    {
        $this->client->queueBind(
            $this->id,
            $queue,
            $exchange,
            $rkey,
            $flags & MQ_NOWAIT,
            $arguments
        );
    }

    /**
     * Tell RabbitMQ to PUSH messages from queue to this channel.
     * If messages are consumed, Channel need to run() to fetch it.
     * Messages already sent to a consumer not available for get().
     *
     * @param string $queue
     * @param array $arguments
     * @param string $tag
     * @param int $flags
     * @return string Consumer tag
     */
    public function consume(string $queue, array $arguments = [], string $tag = '', int $flags = 0) : string
    {
        $ok = $this->bunny->consume(
            $this,
            $queue,
            $tag,
            $flags & MQ_NOLOCAL,
            $flags & MQ_NOACK,
            $flags & MQ_EXCLUSIVE,
            $flags & MQ_NOWAIT,
            $arguments
        );
        return $ok->consumerTag;
    }

    /**
     * Fetch a message from Queue. Pipeline is not run in this case!
     * It will not be returned if already was pushed to a consumer.
     *
     * @param string $queue
     * @return Message|NULL
     */
    public function get(string $queue) : ?Message
    {
        $ret = $this->bunny->get($queue);
        if ($ret instanceof \Bunny\Message) {
            return $this->getContainer()->build(Message::class, [$ret]);
        }
        else return null;
    }

    /**
     * Runs the listen loop. Called by Application on start.
     *
     */
    public function run(...$args)
    {
        return $this->client->run(...$args);
    }
}
