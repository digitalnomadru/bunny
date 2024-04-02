<?php
namespace Amqp\Response;

use Laminas\Diactoros\Response\TextResponse;
use Amqp\Message;
use Amqp\Channel;

class ResponseRepublish extends TextResponse
{
    const STATUS_CODE = 206;
    const STATUS_TEXT = 'Message republished';

    private Message $msg;
    private string  $ex;
    private string  $rkey;

    public function __construct(Message $msg, ?string $ex = null, ?string $rkey = null)
    {
        parent::__construct(static::STATUS_TEXT, static::STATUS_CODE);
        $this->msg  =   $msg;
        $this->ex   ??= $msg->getAttribute('exchange') ?? '';
        $this->rkey ??= $msg->getAttribute('rkey')     ?? '';
    }

    /**
     * Called after pipeline finishes.
     */
    public function __invoke(Channel $ch)
    {
        $msg = $this->msg;
        // if msg was delayed, x-delay again
        if ($msg->hasHeader('x-delay')) {
            $delay = abs($msg['x-delay']);
            $msg = $msg->withHeader('x-delay', $delay);
        }
        $ch->publish($msg, $this->ex, $this->rkey);
    }
}
