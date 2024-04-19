<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Bunny;

use Bunny\Test\Library\SynchronousClientHelper;
use PHPUnit\Framework\TestCase;

class ChannelTest extends TestCase
{
    /**
     * @var SynchronousClientHelper
     */
    private $helper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->helper = new SynchronousClientHelper();
    }



    public function testClose()
    {
        $c = $this->helper->createClient();
        $c->connect();
        $promise = $c->channel()->close();
        $this->assertInstanceOf("React\\Promise\\PromiseInterface", $promise);
        $promise->done(function () use ($c) {
            $c->stop();
        });
        $c->run();

        $this->assertTrue($c->isConnected());
        $this->helper->disconnectClientWithEventLoop($c);
        $this->assertFalse($c->isConnected());
    }

    public function testExchangeDeclare()
    {
        $c = $this->helper->createClient();

        $ch = $c->connect()->channel();
        $ch->exchangeDeclare("test_exchange", "direct", MQ_AUTODELETE);
        $c->disconnect();

        $this->assertTrue($c->isConnected());
        $this->helper->disconnectClientWithEventLoop($c);
        $this->assertFalse($c->isConnected());
    }

    public function testQueueDeclare()
    {
        $c = $this->helper->createClient();

        $ch = $c->connect()->channel();
        $ch->queueDeclare("test_queue", MQ_AUTODELETE);
        $c->disconnect();

        $this->assertTrue($c->isConnected());
        $this->helper->disconnectClientWithEventLoop($c);
        $this->assertFalse($c->isConnected());
    }

    public function testQueueBind()
    {
        $c = $this->helper->createClient();

        $ch = $c->connect()->channel();
        $ch->exchangeDeclare("test_exchange", "direct", MQ_AUTODELETE);
        $ch->queueDeclare("test_queue", MQ_AUTODELETE);
        $ch->queueBind("test_queue", "test_exchange");
        $ch->client->disconnect();

        $this->assertTrue($c->isConnected());
        $this->helper->disconnectClientWithEventLoop($c);
        $this->assertFalse($c->isConnected());
    }

    public function testPublish()
    {
        $c = $this->helper->createClient();

        $ch = $c->connect()->channel();
        $ch->publish("test publish");
        $ch->client->disconnect();

        $this->assertTrue($c->isConnected());
        $this->helper->disconnectClientWithEventLoop($c);
        $this->assertFalse($c->isConnected());
    }

    public function testConsume()
    {
        $c = $this->helper->createClient();

        $ch = $c->connect()->channel();
        $ch->queueDeclare("test_queue", MQ_AUTODELETE);
        $ch->consume(function (Message $msg, Channel $ch, Client $c) {
            $this->assertEquals("hi", $msg->content);
            $c->stop();
        });
        $ch->publish("hi", "", "test_queue");
        $c->run();
        $c->disconnect();

        $this->assertTrue($c->isConnected());
        $this->helper->disconnectClientWithEventLoop($c);
        $this->assertFalse($c->isConnected());
    }

    public function testRun()
    {
        $c = $this->helper->createClient();

        $ch = $c->connect()->channel();
        $ch->queueDeclare("test_queue", MQ_AUTODELETE);
        $ch->publish("hi again", "", "test_queue");
        $ch->consume(function (Message $msg, Channel $ch, Client $c) {
            $this->assertEquals("hi again", $msg->content);
            $c->stop();
        });
        $ch->run();
        $c->disconnect();

        $this->assertTrue($c->isConnected());
        $this->helper->disconnectClientWithEventLoop($c);
        $this->assertFalse($c->isConnected());
    }

    public function testHeaders()
    {
        $c = $this->helper->createClient();

        $ch = $c->connect()->channel();
        $ch->queueDeclare("test_queue", MQ_AUTODELETE);
        $msg = new Message("<b>hi html</b>", ["content-type" => "text/html"]);
        $ch->publish($msg, "", "test_queue");
        $ch->consume(function (Message $msg, Channel $ch, Client $c) {
            $this->assertTrue($msg->hasHeader("content-type"));
            $this->assertEquals("text/html", $msg->getHeader("content-type"));
            $this->assertEquals("<b>hi html</b>", $msg->content);
            $c->stop();
        });
        $ch->run();
        $c->disconnect();

        $this->assertTrue($c->isConnected());
        $this->helper->disconnectClientWithEventLoop($c);
        $this->assertFalse($c->isConnected());
    }

    public function testBigMessage()
    {
        $body = str_repeat("a", 10 << 20 /* 10 MiB */);

        $c = $this->helper->createClient();

        $ch = $c->connect()->channel();
        $ch->queueDeclare("test_queue", MQ_AUTODELETE);
        $ch->publish($body, "", "test_queue");
        $ch->consume(function (Message $msg, Channel $ch, Client $c) use ($body) {
            $this->assertEquals($body, $msg->content);
            $c->stop();
        });
        $ch->run();
        $c->disconnect();

        $this->assertTrue($c->isConnected());
        $this->helper->disconnectClientWithEventLoop($c);
        $this->assertFalse($c->isConnected());
    }
}
