<?php
namespace Amqp\Response;

use Laminas\Diactoros\Response\TextResponse;

class ResponseOk extends TextResponse
{
    const STATUS_CODE = 200;
    const STATUS_TEXT = 'OK';

    public function __construct($text = self::STATUS_TEXT, int $status = self::STATUS_CODE, array $headers = [])
    {
        parent::__construct($text, $status, $headers);
    }
}
