<?php
namespace Amqp\Response;

use Laminas\Diactoros\Response\TextResponse;

class ResponseRepeat extends TextResponse
{
    const STATUS_CODE = 100;
    const STATUS_TEXT = 'Continue';

    public function __construct($text = self::STATUS_TEXT, int $status = self::STATUS_CODE, array $headers = [])
    {
        parent::__construct($text, $status, $headers);
    }
}
