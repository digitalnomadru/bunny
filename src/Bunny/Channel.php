<?php
namespace Bunny;

use Bunny\Exception\ChannelException;
use Bunny\Protocol\AbstractFrame;
use Bunny\Protocol\Buffer;
use Bunny\Protocol\ContentBodyFrame;
use Bunny\Protocol\ContentHeaderFrame;
use Bunny\Protocol\HeartbeatFrame;
use Bunny\Protocol\MethodBasicAckFrame;
use Bunny\Protocol\MethodBasicConsumeOkFrame;
use Bunny\Protocol\MethodBasicDeliverFrame;
use Bunny\Protocol\MethodBasicGetEmptyFrame;
use Bunny\Protocol\MethodBasicGetOkFrame;
use Bunny\Protocol\MethodBasicNackFrame;
use Bunny\Protocol\MethodBasicReturnFrame;
use Bunny\Protocol\MethodChannelCloseFrame;
use Bunny\Protocol\MethodChannelCloseOkFrame;
use Bunny\Protocol\MethodFrame;
use LogicException;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

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
 * AMQP channel.
 *
 * End-user class to interact with RabbitMQ. Proxies methods to client.
 */
class Channel
{
    public int $defaultQueueFlags = MQ_DURABLE;
    public array $defaultQueueArguments = [];

    public int $id;
    public ?AbstractClient $client;
    public int $deliveryTag;

    public int $state = ChannelStateEnum::READY;
    public int $mode = ChannelModeEnum::REGULAR;

    /** @var callable[] */
    protected array $returnCallbacks = [];

    /** @var callable[] */
    protected array $deliverCallbacks = [];

    /** @var callable[] */
    protected array $ackCallbacks = [];

    protected ?MethodBasicReturnFrame $returnFrame = null;

    protected ?MethodBasicDeliverFrame $deliverFrame = null;

    protected ?MethodBasicGetOkFrame $getOkFrame = null;

    protected ?ContentHeaderFrame $headerFrame = null;

    protected int $bodySizeRemaining;

    protected Buffer $bodyBuffer;

    protected ?Deferred $closeDeferred = null;

    protected ?PromiseInterface $closePromise = null;

    protected ?Deferred $getDeferred = null;

    public function __construct(AbstractClient $client, int $channelId)
    {
        $this->client = $client;
        $this->id = $channelId;
        $this->bodyBuffer = new Buffer();
    }

    public function exchangeDeclare(
        string  $name,
        string  $type = 'direct',
        int     $flags = MQ_DURABLE,
        array   $arguments = []
    ): bool|Protocol\MethodExchangeDeclareOkFrame
    {
        return $this->client->exchangeDeclare(
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
    public function exchangeDelete(string $exchange, int $flags = 0): Protocol\MethodExchangeDeleteOkFrame|bool
    {
        return $this->client->exchangeDelete($this->id, $exchange, $flags & MQ_IFUNUSED, $flags & MQ_NOWAIT);
    }

    public function queueDeclare(?string $name = '', ?int $flags = null, array $arguments = []) : string
    {
        $flags ??= $this->defaultQueueFlags;

        $ok = $this->client->queueDeclare(
            $this->id,
            $name ?? '',
            $flags & MQ_PASSIVE,
            $flags & MQ_DURABLE,
            $flags & MQ_EXCLUSIVE,
            $flags & MQ_AUTODELETE,
            $flags & MQ_NOWAIT,
            array_merge($this->defaultQueueArguments, $arguments)
        );
        return $ok->queue;
    }

    /**
     * Delete all messages.
     */
    public function queuePurge(string $name, bool $nowait = false): bool|Protocol\MethodQueuePurgeOkFrame
    {
        return $this->client->queuePurge($this->id, $name, $nowait);
    }

    public function queueDelete(string $queue, int $flags = 0): Protocol\MethodQueueDeleteOkFrame|bool
    {
        return $this->client->queueDelete(
            $this->id,
            $queue,
            $flags & MQ_IFUNUSED,
            $flags & MQ_IFEMPTY,
            $flags & MQ_NOWAIT
        );
    }

    public function exchangeBind(
        string $destination,
        string $source,
        string $routingKey = '',
        bool $nowait = false,
        array $arguments = []
    ): Protocol\MethodExchangeBindOkFrame|bool
    {
        return $this->client->exchangeBind($this->id, $destination, $source, $routingKey, $nowait, $arguments);
    }

    public function exchangeUnbind(
        string $destination,
        string $source,
        string $routingKey = '',
        bool   $nowait = false,
        array  $arguments = []
    ): bool|Protocol\MethodExchangeUnbindOkFrame
    {
        return $this->client->exchangeUnbind($this->id, $destination, $source, $routingKey, $nowait, $arguments);
    }

    public function queueBind(
        string $queue,
        string $exchange,
        string $routingKey = '',
        int $flags = 0,
        array $arguments = []
    ): Protocol\MethodQueueBindOkFrame|bool
    {
        return $this->client->queueBind($this->id, $queue, $exchange, $routingKey, $flags & MQ_NOWAIT, $arguments);
    }

    public function queueUnbind(
        string $queue,
        string $exchange,
        string $routingKey = '',
        array $arguments = []
    ): Protocol\MethodQueueUnbindOkFrame
    {
        return $this->client->queueUnbind($this->id, $queue, $exchange, $routingKey, $arguments);
    }

    /**
     * Listener is called whenever 'basic.return' frame is received with arguments (Message $returnedMessage, MethodBasicReturnFrame $frame)
     *
     */
    public function addReturnListener(callable $callback): static
    {
        $this->removeReturnListener($callback); // remove if previously added to prevent calling multiple times
        $this->returnCallbacks[] = $callback;
        return $this;
    }

    /**
     * Removes registered return listener. If the callback is not registered, this is noop.
     *
     */
    public function removeReturnListener(callable $callback): static
    {
        foreach ($this->returnCallbacks as $k => $v) {
            if ($v === $callback) {
                unset($this->returnCallbacks[$k]);
            }
        }

        return $this;
    }

    /**
     * Listener is called whenever 'basic.ack' or 'basic.nack' is received.
     *
     */
    public function addAckListener(callable $callback): static
    {
        if ($this->mode !== ChannelModeEnum::CONFIRM) {
            throw new ChannelException("Ack/nack listener can be added when channel in confirm mode.");
        }

        $this->removeAckListener($callback);
        $this->ackCallbacks[] = $callback;
        return $this;
    }

    /**
     * Removes registered ack/nack listener. If the callback is not registered, this is noop.
     *
     */
    public function removeAckListener(callable $callback): static
    {
        if ($this->mode !== ChannelModeEnum::CONFIRM) {
            throw new ChannelException("Ack/nack listener can be removed when channel in confirm mode.");
        }

        foreach ($this->ackCallbacks as $k => $v) {
            if ($v === $callback) {
                unset($this->ackCallbacks[$k]);
            }
        }

        return $this;
    }

    /**
     * Closes channel.
     *
     * Always returns a promise, because there can be outstanding messages to be processed.
     *
     */
    public function close(int $replyCode = 0, string $replyText = ""): PromiseInterface
    {
        if ($this->state === ChannelStateEnum::CLOSED) {
            throw new ChannelException("Trying to close already closed channel #$this->id.");
        }

        if ($this->state === ChannelStateEnum::CLOSING) {
            return $this->closePromise;
        }

        $this->state = ChannelStateEnum::CLOSING;

        $this->client->channelClose($this->id, $replyCode, $replyText, 0, 0);
        $this->closeDeferred = new Deferred();
        return $this->closePromise = $this->closeDeferred->promise()->then(function () {
            $this->client->removeChannel($this->id);
        });
    }


    /**
     * Tell RabbitMQ to PUSH messages from queue to this channel.
     * If messages are consumed, Channel need to run() to fetch it.
     * Messages already sent to a consumer not available for get().
     *
     * @return string Consumer tag
     */
    public function consume(callable $callback, string $queue = '', string $tag = '', int $flags = 0, array $arguments = []) : string
    {
        $response = $this->client->consume(
            $this->id,
            $queue,
            $tag,
            $flags & MQ_NOLOCAL,
            $flags & MQ_NOACK,
            $flags & MQ_EXCLUSIVE,
            $flags & MQ_NOWAIT,
            $arguments
        );

        if ($response instanceof MethodBasicConsumeOkFrame) {
            $this->deliverCallbacks[$response->consumerTag] = $callback;
            return $response->consumerTag;

        }
        else {
            throw new ChannelException(
                "basic.consume unexpected response of type " . gettype($response) .
                (is_object($response) ? " (" . get_class($response) . ")" : "") .
                "."
            );
        }
    }

    /**
     * Convenience method that registers consumer and then starts client event loop.
     *
     */
    public function run(int $maxSeconds = null): void
    {
        $this->client->run($maxSeconds);
    }

    /**
     * Acks given message.
     *
     */
    public function ack(Message $message, $multiple = false): bool
    {
        return $this->client->ack($this->id, $message->deliveryTag, $multiple);
    }

    /**
     * Nacks given message.
     *
     */
    public function nack(Message $message, bool $multiple = false, bool $requeue = true): bool
    {
        return $this->client->nack($this->id, $message->deliveryTag, $multiple, $requeue);
    }

    /**
     * Rejects given message.
     *
     */
    public function reject(Message $message, bool $requeue = true): bool
    {
        return $this->client->reject($this->id, $message->deliveryTag, $requeue);
    }

    /**
     * Synchronously returns message if there is any waiting in the queue.
     *
     */
    public function get(string $queue = "", bool $noAck = false): Message|null
    {
        if ($this->getDeferred !== null) {
            throw new ChannelException("Another 'basic.get' already in progress. You should use 'basic.consume' instead of multiple 'basic.get'.");
        }

        $response = $this->client->get($this->id, $queue, $noAck);

        if ($response instanceof MethodBasicGetEmptyFrame) {
            return null;

        } elseif ($response instanceof MethodBasicGetOkFrame) {
            $this->state = ChannelStateEnum::AWAITING_HEADER;

            $headerFrame = $this->client->awaitContentHeader($this->id);
            $this->headerFrame = $headerFrame;
            $this->bodySizeRemaining = $headerFrame->bodySize;
            $this->state = ChannelStateEnum::AWAITING_BODY;

            while ($this->bodySizeRemaining > 0) {
                $bodyFrame = $this->client->awaitContentBody($this->id);

                $this->bodyBuffer->append($bodyFrame->payload);
                $this->bodySizeRemaining -= $bodyFrame->payloadSize;

                if ($this->bodySizeRemaining < 0) {
                    $this->state = ChannelStateEnum::ERROR;
                    $this->client->disconnect(Constants::STATUS_SYNTAX_ERROR, $errorMessage = "Body overflow, received " . (-$this->bodySizeRemaining) . " more bytes.");
                    throw new ChannelException($errorMessage);
                }
            }

            $this->state = ChannelStateEnum::READY;

            $message = new Message(
                null,
                $response->deliveryTag,
                $response->redelivered,
                $response->exchange,
                $response->routingKey,
                $this->headerFrame->toArray(),
                $this->bodyBuffer->consume($this->bodyBuffer->getLength())
            );

            $this->headerFrame = null;

            return $message;

        } else {
            throw new LogicException("This statement should never be reached.");
        }
    }

    /**
     * Published message to given exchange.
     *
     * @param string $body
     * @param array $headers
     * @param string $exchange
     * @param string $routingKey
     * @param bool $mandatory
     * @param bool $immediate
     */
    public function publish($body, array $headers = [], $exchange = '', $routingKey = '', $mandatory = false, $immediate = false): bool|int
    {
        $response = $this->client->publish($this->id, $body, $headers, $exchange, $routingKey, $mandatory, $immediate);

        if ($this->mode === ChannelModeEnum::CONFIRM) {
            return ++$this->deliveryTag;
        } else {
            return $response;
        }
    }

    /**
     * Cancels given consumer subscription.
     *
     * @param string $consumerTag
     * @param bool $nowait
     */
    public function cancel($consumerTag, $nowait = false): Protocol\MethodBasicCancelOkFrame|bool
    {
        $response = $this->client->cancel($this->id, $consumerTag, $nowait);
        unset($this->deliverCallbacks[$consumerTag]);
        return $response;
    }

    public function qos($prefetchSize = 0, $prefetchCount = 0, $global = false): Protocol\MethodBasicQosOkFrame
    {
        return $this->client->qos($this->id, $prefetchSize, $prefetchCount, $global);
    }

    public function recover($requeue = false): Protocol\MethodBasicRecoverOkFrame
    {
        return $this->client->recover($this->id, $requeue);
    }

    /**
     * Changes channel to transactional mode. All messages are published to queues only after {@link txCommit()} is called.
     *
     */
    public function txSelect(): Protocol\MethodTxSelectOkFrame
    {
        if ($this->mode !== ChannelModeEnum::REGULAR) {
            throw new ChannelException("Channel not in regular mode, cannot change to transactional mode.");
        }

        $response = $this->client->txSelect($this->id);

        $this->mode = ChannelModeEnum::TRANSACTIONAL;
        return $response;
    }

    /**
     * Commit transaction.
     *
     */
    public function txCommit(): Protocol\MethodTxCommitOkFrame
    {
        if ($this->mode !== ChannelModeEnum::TRANSACTIONAL) {
            throw new ChannelException("Channel not in transactional mode, cannot call 'tx.commit'.");
        }

        return $this->client->txCommit($this->id);
    }

    /**
     * Rollback transaction.
     *
     */
    public function txRollback(): Protocol\MethodTxRollbackOkFrame
    {
        if ($this->mode !== ChannelModeEnum::TRANSACTIONAL) {
            throw new ChannelException("Channel not in transactional mode, cannot call 'tx.rollback'.");
        }

        return $this->client->txRollback($this->id);
    }

    /**
     * Changes channel to confirm mode. Broker then asynchronously sends 'basic.ack's for published messages.
     *
     */
    public function confirmSelect(callable $callback = null, bool $nowait = false): Protocol\MethodConfirmSelectOkFrame
    {
        if ($this->mode !== ChannelModeEnum::REGULAR) {
            throw new ChannelException("Channel not in regular mode, cannot change to transactional mode.");
        }

        $response = $this->client->confirmSelect($this->id, $nowait);

        $this->enterConfirmMode($callback);
        return $response;
    }

    private function enterConfirmMode(callable $callback = null): void
    {
        $this->mode = ChannelModeEnum::CONFIRM;
        $this->deliveryTag = 0;

        if ($callback) {
            $this->addAckListener($callback);
        }
    }

    /**
     * Callback after channel-level frame has been received.
     *
     */
    public function onFrameReceived(AbstractFrame $frame): void
    {
        if ($this->state === ChannelStateEnum::ERROR) {
            throw new ChannelException("Channel in error state.");
        }

        if ($this->state === ChannelStateEnum::CLOSED) {
            throw new ChannelException("Received frame #$frame->type on closed channel #$this->id.");
        }

        if ($frame instanceof MethodFrame) {
            if ($this->state === ChannelStateEnum::CLOSING && !($frame instanceof MethodChannelCloseOkFrame)) {
                // drop frames in closing state
                return;

            } elseif ($this->state !== ChannelStateEnum::READY && !($frame instanceof MethodChannelCloseOkFrame)) {
                $currentState = $this->state;
                $this->state = ChannelStateEnum::ERROR;

                if ($currentState === ChannelStateEnum::AWAITING_HEADER) {
                    $msg = "Got method frame, expected header frame.";
                } elseif ($currentState === ChannelStateEnum::AWAITING_BODY) {
                    $msg = "Got method frame, expected body frame.";
                } else {
                    throw new LogicException("Unhandled channel state.");
                }

                $this->client->disconnect(Constants::STATUS_UNEXPECTED_FRAME, $msg);

                throw new ChannelException("Unexpected frame: " . $msg);
            }

            if ($frame instanceof MethodChannelCloseOkFrame) {
                $this->state = ChannelStateEnum::CLOSED;

                if ($this->closeDeferred !== null) {
                    $this->closeDeferred->resolve($this->id);
                }

                // break reference cycle, must be called after resolving promise
                $this->client = null;
                // break consumers' reference cycle
                $this->deliverCallbacks = [];

            } elseif ($frame instanceof MethodBasicReturnFrame) {
                $this->returnFrame = $frame;
                $this->state = ChannelStateEnum::AWAITING_HEADER;

            } elseif ($frame instanceof MethodBasicDeliverFrame) {
                $this->deliverFrame = $frame;
                $this->state = ChannelStateEnum::AWAITING_HEADER;

            } elseif ($frame instanceof MethodBasicAckFrame) {
                foreach ($this->ackCallbacks as $callback) {
                    $callback($frame);
                }

            } elseif ($frame instanceof MethodBasicNackFrame) {
                foreach ($this->ackCallbacks as $callback) {
                    $callback($frame);
                }
            } elseif ($frame instanceof MethodChannelCloseFrame) {
                throw new ChannelException("Channel closed by server: " . $frame->replyText, $frame->replyCode);

            } else {
                throw new ChannelException("Unhandled method frame " . get_class($frame) . ".");
            }

        } elseif ($frame instanceof ContentHeaderFrame) {
            if ($this->state === ChannelStateEnum::CLOSING) {
                // drop frames in closing state
                return;

            } elseif ($this->state !== ChannelStateEnum::AWAITING_HEADER) {
                $currentState = $this->state;
                $this->state = ChannelStateEnum::ERROR;

                if ($currentState === ChannelStateEnum::READY) {
                    $msg = "Got header frame, expected method frame.";
                } elseif ($currentState === ChannelStateEnum::AWAITING_BODY) {
                    $msg = "Got header frame, expected content frame.";
                } else {
                    throw new LogicException("Unhandled channel state.");
                }

                $this->client->disconnect(Constants::STATUS_UNEXPECTED_FRAME, $msg);

                throw new ChannelException("Unexpected frame: " . $msg);
            }

            $this->headerFrame = $frame;
            $this->bodySizeRemaining = $frame->bodySize;

            if ($this->bodySizeRemaining > 0) {
                $this->state = ChannelStateEnum::AWAITING_BODY;
            } else {
                $this->state = ChannelStateEnum::READY;
                $this->onBodyComplete();
            }

        } elseif ($frame instanceof ContentBodyFrame) {
            if ($this->state === ChannelStateEnum::CLOSING) {
                // drop frames in closing state
                return;

            } elseif ($this->state !== ChannelStateEnum::AWAITING_BODY) {
                $currentState = $this->state;
                $this->state = ChannelStateEnum::ERROR;

                if ($currentState === ChannelStateEnum::READY) {
                    $msg = "Got body frame, expected method frame.";
                } elseif ($currentState === ChannelStateEnum::AWAITING_HEADER) {
                    $msg = "Got body frame, expected header frame.";
                } else {
                    throw new LogicException("Unhandled channel state.");
                }

                $this->client->disconnect(Constants::STATUS_UNEXPECTED_FRAME, $msg);

                throw new ChannelException("Unexpected frame: " . $msg);
            }

            $this->bodyBuffer->append($frame->payload);
            $this->bodySizeRemaining -= $frame->payloadSize;

            if ($this->bodySizeRemaining < 0) {
                $this->state = ChannelStateEnum::ERROR;
                $this->client->disconnect(Constants::STATUS_SYNTAX_ERROR, "Body overflow, received " . (-$this->bodySizeRemaining) . " more bytes.");

            } elseif ($this->bodySizeRemaining === 0) {
                $this->state = ChannelStateEnum::READY;
                $this->onBodyComplete();
            }

        } elseif ($frame instanceof HeartbeatFrame) {
            $this->client->disconnect(Constants::STATUS_UNEXPECTED_FRAME, "Got heartbeat on non-zero channel.");
            throw new ChannelException("Unexpected heartbeat frame.");

        } else {
            throw new ChannelException("Unhandled frame " . get_class($frame) . ".");
        }
    }

    /**
     * Callback after content body has been completely received.
     */
    protected function onBodyComplete(): void
    {
        if ($this->returnFrame) {
            $content = $this->bodyBuffer->consume($this->bodyBuffer->getLength());
            $message = new Message(
                null,
                null,
                false,
                $this->returnFrame->exchange,
                $this->returnFrame->routingKey,
                $this->headerFrame->toArray(),
                $content
            );

            foreach ($this->returnCallbacks as $callback) {
                $callback($message, $this->returnFrame);
            }

            $this->returnFrame = null;
            $this->headerFrame = null;

        } elseif ($this->deliverFrame) {
            $content = $this->bodyBuffer->consume($this->bodyBuffer->getLength());
            if (isset($this->deliverCallbacks[$this->deliverFrame->consumerTag])) {
                $message = new Message(
                    $this->deliverFrame->consumerTag,
                    $this->deliverFrame->deliveryTag,
                    $this->deliverFrame->redelivered,
                    $this->deliverFrame->exchange,
                    $this->deliverFrame->routingKey,
                    $this->headerFrame->toArray(),
                    $content
                );

                $callback = $this->deliverCallbacks[$this->deliverFrame->consumerTag];

                $callback($message, $this, $this->client);
            }

            $this->deliverFrame = null;
            $this->headerFrame = null;

        } elseif ($this->getOkFrame) {
            $content = $this->bodyBuffer->consume($this->bodyBuffer->getLength());

            // deferred has to be first nullified and then resolved, otherwise results in race condition
            $deferred = $this->getDeferred;
            $this->getDeferred = null;
            $deferred->resolve(new Message(
                null,
                $this->getOkFrame->deliveryTag,
                $this->getOkFrame->redelivered,
                $this->getOkFrame->exchange,
                $this->getOkFrame->routingKey,
                $this->headerFrame->toArray(),
                $content
            ));

            $this->getOkFrame = null;
            $this->headerFrame = null;

        } else {
            throw new LogicException("Either return or deliver frame has to be handled here.");
        }
    }
}
