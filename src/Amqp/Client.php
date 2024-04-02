<?php
namespace Amqp;

use Bunny\Exception\ClientException;
use Bunny\Protocol\AbstractFrame;
use Bunny\Protocol\HeartbeatFrame;
use Bunny\ChannelStateEnum;
/**
 * Never used directly in App. Fetch Channel::class instead.
 */
class Client extends \Bunny\Client
{
    /**
     * @var \Bunny\Channel
     */
    protected $channel;

    public function __construct(array $config = [])
    {
        $options = @$config['rabbitmq'] ?? [];
        parent::__construct($options);
    }

    /**
     * Runs it's own event loop, processes frames as they arrive. Processes messages for at most $maxSeconds.
     *
     * @param float $maxSeconds
     */
    public function run($maxSeconds = null, $maxMessages = null)
    {
        if (!$this->isConnected()) {
            throw new ClientException("Client has to be connected.");
        }

        $this->running = true;
        $startTime = microtime(true);
        $stopTime = null;
        if ($maxSeconds !== null) {
            $stopTime = $startTime + $maxSeconds;
        }

        do {
            if (!empty($this->queue)) {
                $frame = array_shift($this->queue);

            } else {
                if (($frame = $this->reader->consumeFrame($this->readBuffer)) === null) {
                    $now = microtime(true);
                    $nextStreamSelectTimeout = $nextHeartbeat = ($this->lastWrite ?: $now) + $this->options["heartbeat"];
                    if ($stopTime !== null && $stopTime < $nextStreamSelectTimeout) {
                        $nextStreamSelectTimeout = $stopTime;
                    }
                    $tvSec = max(intval($nextStreamSelectTimeout - $now), 0);
                    $tvUsec = max(intval(($nextStreamSelectTimeout - $now - $tvSec) * 1000000), 0);

                    $r = [$this->getStream()];
                    $w = null;
                    $e = null;

                    if (($n = @stream_select($r, $w, $e, $tvSec, $tvUsec)) === false) {
                        $lastError = error_get_last();
                        if ($lastError !== null &&
                            preg_match("/^stream_select\\(\\): unable to select \\[(\\d+)\\]:/", $lastError["message"], $m) &&
                            intval($m[1]) === PCNTL_EINTR
                        ) {
                            // got interrupted by signal, dispatch signals & continue
                            pcntl_signal_dispatch();
                            $n = 0;

                        } else {
                            throw new ClientException('RabbitMQ stream is over.');
                        }
                    }

                    $now = microtime(true);

                    if ($now >= $nextHeartbeat) {
                        $this->writer->appendFrame(new HeartbeatFrame(), $this->writeBuffer);
                        $this->flushWriteBuffer();
                    }

                    if ($stopTime !== null && $now >= $stopTime) {
                        break;
                    }

                    if ($n > 0) {
                        $this->feedReadBuffer();
                    }

                    continue;
                }
            }

            /** @var AbstractFrame $frame */

            if ($frame->channel === 0) {
                $this->onFrameReceived($frame);

            } else {
                if (!isset($this->channels[$frame->channel])) {
                    throw new ClientException(
                        "Received frame #{$frame->type} on closed channel #{$frame->channel}."
                    );
                }

                $ch = $this->channels[$frame->channel];
                $ch->onFrameReceived($frame);

                if ($ch->state == ChannelStateEnum::READY) {
                    if ($maxMessages !== null && --$maxMessages <=0 ) {
                        $r = @$ch->lastResponse;
                        unset($ch->lastResponse);
                        return $r;
                    }
                }
            }


        } while ($this->running);
    }
}
