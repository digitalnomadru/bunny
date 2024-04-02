<?php
namespace Amqp\Response;

use Laminas\Diactoros\Response\TextResponse;

class ResponseNack extends TextResponse
{
    const STATUS_CODE = 501;
    const STATUS_TEXT = 'Try again.';

    public function __construct($text = self::STATUS_TEXT, int $status = self::STATUS_CODE, array $headers = [])
    {
        parent::__construct($text, $status, $headers);
    }
}
