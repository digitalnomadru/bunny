<?php
namespace Amqp\Response;

use Laminas\Diactoros\Response\TextResponse;

class ResponseReject extends TextResponse
{
    const STATUS_CODE = 400;
    const STATUS_TEXT = '';

    public function __construct($text = self::STATUS_TEXT, int $status = self::STATUS_CODE, array $headers = [])
    {
        parent::__construct($text, $status, $headers);
    }
}
