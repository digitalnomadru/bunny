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
 * - Closely works with underlying client instance.
 * - Manages consumers.
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class Channel
{
    use ChannelMethods {
        ChannelMethods::consume as private consumeImpl;
        ChannelMethods::ack as private ackImpl;
        ChannelMethods::reject as private rejectImpl;
        ChannelMethods::nack as private nackImpl;
        ChannelMethods::get as private getImpl;
        ChannelMethods::publish as private publishImpl;
        ChannelMethods::cancel as private cancelImpl;
        ChannelMethods::txSelect as private txSelectImpl;
        ChannelMethods::txCommit as private txCommitImpl;
        ChannelMethods::txRollback as private txRollbackImpl;
        ChannelMethods::confirmSelect as private confirmSelectImpl;
    }

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
     * Creates new consumer on channel.
     *
     */
    public function consume(
        callable $callback,
        string $queue = "",
        string $consumerTag = "",
        bool $noLocal = false,
        bool $noAck = false,
        bool $exclusive = false,
        bool $nowait = false,
        array $arguments = []
    ): MethodBasicConsumeOkFrame|PromiseInterface
    {
        $response = $this->consumeImpl($queue, $consumerTag, $noLocal, $noAck, $exclusive, $nowait, $arguments);

        if ($response instanceof MethodBasicConsumeOkFrame) {
            $this->deliverCallbacks[$response->consumerTag] = $callback;
            return $response;

        } elseif ($response instanceof PromiseInterface) {
            return $response->then(function (MethodBasicConsumeOkFrame $response) use ($callback) {
                $this->deliverCallbacks[$response->consumerTag] = $callback;
                return $response;
            });

        } else {
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
    public function run(
        callable $callback,
        string $queue = "",
        string $consumerTag = "",
        bool $noLocal = false,
        bool $noAck = false,
        bool $exclusive = false,
        bool $nowait = false,
        array $arguments = []
    ): void
    {
        $response = $this->consume($callback, $queue, $consumerTag, $noLocal, $noAck, $exclusive, $nowait, $arguments);

        if ($response instanceof MethodBasicConsumeOkFrame) {
            $this->client->run();

        } elseif ($response instanceof PromiseInterface) {
            $response->done(function () {
                $this->client->run();
            });

        } else {
            throw new ChannelException(
                "Unexpected response of type " . gettype($response) .
                (is_object($response) ? " (" . get_class($response) . ")" : "") .
                "."
            );
        }
    }

    /**
     * Acks given message.
     *
     */
    public function ack(Message $message, $multiple = false): PromiseInterface|bool
    {
        return $this->ackImpl($message->deliveryTag, $multiple);
    }

    /**
     * Nacks given message.
     *
     */
    public function nack(Message $message, bool $multiple = false, bool $requeue = true): PromiseInterface|bool
    {
        return $this->nackImpl($message->deliveryTag, $multiple, $requeue);
    }

    /**
     * Rejects given message.
     *
     */
    public function reject(Message $message, bool $requeue = true): PromiseInterface|bool
    {
        return $this->rejectImpl($message->deliveryTag, $requeue);
    }

    /**
     * Synchronously returns message if there is any waiting in the queue.
     *
     */
    public function get(string $queue = "", bool $noAck = false): PromiseInterface|Message|null
    {
        if ($this->getDeferred !== null) {
            throw new ChannelException("Another 'basic.get' already in progress. You should use 'basic.consume' instead of multiple 'basic.get'.");
        }

        $response = $this->getImpl($queue, $noAck);

        if ($response instanceof PromiseInterface) {
            $this->getDeferred = new Deferred();

            $response->done(function ($frame) {
                if ($frame instanceof MethodBasicGetEmptyFrame) {
                    // deferred has to be first nullified and then resolved, otherwise results in race condition
                    $deferred = $this->getDeferred;
                    $this->getDeferred = null;
                    $deferred->resolve(null);

                } elseif ($frame instanceof MethodBasicGetOkFrame) {
                    $this->getOkFrame = $frame;
                    $this->state = ChannelStateEnum::AWAITING_HEADER;

                } else {
                    throw new \LogicException("This statement should never be reached.");
                }
            });

            return $this->getDeferred->promise();

        } elseif ($response instanceof MethodBasicGetEmptyFrame) {
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
            throw new \LogicException("This statement should never be reached.");
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
    public function publish($body, array $headers = [], $exchange = '', $routingKey = '', $mandatory = false, $immediate = false): PromiseInterface|bool|int
    {
        $response = $this->publishImpl($body, $headers, $exchange, $routingKey, $mandatory, $immediate);

        if ($this->mode === ChannelModeEnum::CONFIRM) {
            if ($response instanceof PromiseInterface) {
                return $response->then(function () {
                    return ++$this->deliveryTag;
                });
            } else {
                return ++$this->deliveryTag;
            }
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
    public function cancel($consumerTag, $nowait = false): Protocol\MethodBasicCancelOkFrame|PromiseInterface|bool
    {
        $response = $this->cancelImpl($consumerTag, $nowait);
        unset($this->deliverCallbacks[$consumerTag]);
        return $response;
    }

    /**
     * Changes channel to transactional mode. All messages are published to queues only after {@link txCommit()} is called.
     *
     */
    public function txSelect(): PromiseInterface|Protocol\MethodTxSelectOkFrame
    {
        if ($this->mode !== ChannelModeEnum::REGULAR) {
            throw new ChannelException("Channel not in regular mode, cannot change to transactional mode.");
        }

        $response = $this->txSelectImpl();

        if ($response instanceof PromiseInterface) {
            return $response->then(function ($response) {
                $this->mode = ChannelModeEnum::TRANSACTIONAL;
                return $response;
            });

        } else {
            $this->mode = ChannelModeEnum::TRANSACTIONAL;
            return $response;
        }
    }

    /**
     * Commit transaction.
     *
     */
    public function txCommit(): Protocol\MethodTxCommitOkFrame|PromiseInterface
    {
        if ($this->mode !== ChannelModeEnum::TRANSACTIONAL) {
            throw new ChannelException("Channel not in transactional mode, cannot call 'tx.commit'.");
        }

        return $this->txCommitImpl();
    }

    /**
     * Rollback transaction.
     *
     */
    public function txRollback(): PromiseInterface|Protocol\MethodTxRollbackOkFrame
    {
        if ($this->mode !== ChannelModeEnum::TRANSACTIONAL) {
            throw new ChannelException("Channel not in transactional mode, cannot call 'tx.rollback'.");
        }

        return $this->txRollbackImpl();
    }

    /**
     * Changes channel to confirm mode. Broker then asynchronously sends 'basic.ack's for published messages.
     *
     */
    public function confirmSelect(callable $callback = null, bool $nowait = false): PromiseInterface|Protocol\MethodConfirmSelectOkFrame
    {
        if ($this->mode !== ChannelModeEnum::REGULAR) {
            throw new ChannelException("Channel not in regular mode, cannot change to transactional mode.");
        }

        $response = $this->confirmSelectImpl($nowait);

        if ($response instanceof PromiseInterface) {
            return $response->then(function ($response) use ($callback) {
                $this->enterConfirmMode($callback);
                return $response;
            });

        } else {
            $this->enterConfirmMode($callback);
            return $response;
        }
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
                    throw new \LogicException("Unhandled channel state.");
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
                    throw new \LogicException("Unhandled channel state.");
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
                    throw new \LogicException("Unhandled channel state.");
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
            throw new \LogicException("Either return or deliver frame has to be handled here.");
        }
    }
}
