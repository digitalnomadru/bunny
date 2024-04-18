<?php
namespace Bunny;

use Bunny\Protocol;
use React\Promise;

/**
 * AMQP-0-9-1 channel methods
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
trait ChannelMethods
{
    public int $id;
    public ?AbstractClient $client;

    /**
     * Calls exchange.declare AMQP method.
     *
     * @param string $exchange
     * @param string $exchangeType
     * @param boolean $passive
     * @param boolean $durable
     * @param boolean $autoDelete
     * @param boolean $internal
     * @param boolean $nowait
     * @param array $arguments
     *
     * @return boolean|Promise\PromiseInterface|Protocol\MethodExchangeDeclareOkFrame
     */
    public function exchangeDeclare($exchange, $exchangeType = 'direct', $passive = false, $durable = false, $autoDelete = false, $internal = false, $nowait = false, $arguments = [])
    {
        return $this->client->exchangeDeclare($this->id, $exchange, $exchangeType, $passive, $durable, $autoDelete, $internal, $nowait, $arguments);
    }

    /**
     * Calls exchange.delete AMQP method.
     *
     * @param string $exchange
     * @param boolean $ifUnused
     * @param boolean $nowait
     *
     * @return boolean|Promise\PromiseInterface|Protocol\MethodExchangeDeleteOkFrame
     */
    public function exchangeDelete($exchange, $ifUnused = false, $nowait = false)
    {
        return $this->client->exchangeDelete($this->id, $exchange, $ifUnused, $nowait);
    }

    /**
     * Calls exchange.bind AMQP method.
     *
     * @param string $destination
     * @param string $source
     * @param string $routingKey
     * @param boolean $nowait
     * @param array $arguments
     *
     * @return boolean|Promise\PromiseInterface|Protocol\MethodExchangeBindOkFrame
     */
    public function exchangeBind($destination, $source, $routingKey = '', $nowait = false, $arguments = [])
    {
        return $this->client->exchangeBind($this->id, $destination, $source, $routingKey, $nowait, $arguments);
    }

    /**
     * Calls exchange.unbind AMQP method.
     *
     * @param string $destination
     * @param string $source
     * @param string $routingKey
     * @param boolean $nowait
     * @param array $arguments
     *
     * @return boolean|Promise\PromiseInterface|Protocol\MethodExchangeUnbindOkFrame
     */
    public function exchangeUnbind($destination, $source, $routingKey = '', $nowait = false, $arguments = [])
    {
        return $this->client->exchangeUnbind($this->id, $destination, $source, $routingKey, $nowait, $arguments);
    }

    /**
     * Calls queue.declare AMQP method.
     *
     * @param string $queue
     * @param boolean $passive
     * @param boolean $durable
     * @param boolean $exclusive
     * @param boolean $autoDelete
     * @param boolean $nowait
     * @param array $arguments
     *
     * @return boolean|Promise\PromiseInterface|Protocol\MethodQueueDeclareOkFrame
     */
    public function queueDeclare($queue = '', $passive = false, $durable = false, $exclusive = false, $autoDelete = false, $nowait = false, $arguments = [])
    {
        return $this->client->queueDeclare($this->id, $queue, $passive, $durable, $exclusive, $autoDelete, $nowait, $arguments);
    }

    /**
     * Calls queue.bind AMQP method.
     *
     * @param string $queue
     * @param string $exchange
     * @param string $routingKey
     * @param boolean $nowait
     * @param array $arguments
     *
     * @return boolean|Promise\PromiseInterface|Protocol\MethodQueueBindOkFrame
     */
    public function queueBind($queue, $exchange, $routingKey = '', $nowait = false, $arguments = [])
    {
        return $this->client->queueBind($this->id, $queue, $exchange, $routingKey, $nowait, $arguments);
    }

    /**
     * Calls queue.purge AMQP method.
     *
     * @param string $queue
     * @param boolean $nowait
     *
     * @return boolean|Promise\PromiseInterface|Protocol\MethodQueuePurgeOkFrame
     */
    public function queuePurge($queue = '', $nowait = false)
    {
        return $this->client->queuePurge($this->id, $queue, $nowait);
    }

    /**
     * Calls queue.delete AMQP method.
     *
     * @param string $queue
     * @param boolean $ifUnused
     * @param boolean $ifEmpty
     * @param boolean $nowait
     *
     * @return boolean|Promise\PromiseInterface|Protocol\MethodQueueDeleteOkFrame
     */
    public function queueDelete($queue = '', $ifUnused = false, $ifEmpty = false, $nowait = false)
    {
        return $this->client->queueDelete($this->id, $queue, $ifUnused, $ifEmpty, $nowait);
    }

    /**
     * Calls queue.unbind AMQP method.
     *
     * @param string $queue
     * @param string $exchange
     * @param string $routingKey
     * @param array $arguments
     *
     * @return boolean|Promise\PromiseInterface|Protocol\MethodQueueUnbindOkFrame
     */
    public function queueUnbind($queue, $exchange, $routingKey = '', $arguments = [])
    {
        return $this->client->queueUnbind($this->id, $queue, $exchange, $routingKey, $arguments);
    }

    /**
     * Calls basic.qos AMQP method.
     *
     * @param int $prefetchSize
     * @param int $prefetchCount
     * @param boolean $global
     *
     * @return boolean|Promise\PromiseInterface|Protocol\MethodBasicQosOkFrame
     */
    public function qos($prefetchSize = 0, $prefetchCount = 0, $global = false)
    {
        return $this->client->qos($this->id, $prefetchSize, $prefetchCount, $global);
    }

    /**
     * Calls basic.consume AMQP method.
     *
     * @param string $queue
     * @param string $consumerTag
     * @param boolean $noLocal
     * @param boolean $noAck
     * @param boolean $exclusive
     * @param boolean $nowait
     * @param array $arguments
     *
     * @return boolean|Promise\PromiseInterface|Protocol\MethodBasicConsumeOkFrame
     */
    public function consume($queue = '', $consumerTag = '', $noLocal = false, $noAck = false, $exclusive = false, $nowait = false, $arguments = [])
    {
        return $this->client->consume($this->id, $queue, $consumerTag, $noLocal, $noAck, $exclusive, $nowait, $arguments);
    }

    /**
     * Calls basic.cancel AMQP method.
     *
     * @param string $consumerTag
     * @param boolean $nowait
     *
     * @return boolean|Promise\PromiseInterface|Protocol\MethodBasicCancelOkFrame
     */
    public function cancel($consumerTag, $nowait = false)
    {
        return $this->client->cancel($this->id, $consumerTag, $nowait);
    }

    /**
     * Calls basic.publish AMQP method.
     *
     * @param string $body
     * @param array $headers
     * @param string $exchange
     * @param string $routingKey
     * @param boolean $mandatory
     * @param boolean $immediate
     *
     * @return boolean|Promise\PromiseInterface
     */
    public function publish($body, array $headers = [], $exchange = '', $routingKey = '', $mandatory = false, $immediate = false)
    {
        return $this->client->publish($this->id, $body, $headers, $exchange, $routingKey, $mandatory, $immediate);
    }

    /**
     * Calls basic.get AMQP method.
     *
     * @param string $queue
     * @param boolean $noAck
     *
     * @return boolean|Promise\PromiseInterface|Protocol\MethodBasicGetOkFrame|Protocol\MethodBasicGetEmptyFrame
     */
    public function get($queue = '', $noAck = false)
    {
        return $this->client->get($this->id, $queue, $noAck);
    }

    /**
     * Calls basic.ack AMQP method.
     *
     * @param int $deliveryTag
     * @param boolean $multiple
     *
     * @return boolean|Promise\PromiseInterface
     */
    public function ack($deliveryTag = 0, $multiple = false)
    {
        return $this->client->ack($this->id, $deliveryTag, $multiple);
    }

    /**
     * Calls basic.reject AMQP method.
     *
     * @param int $deliveryTag
     * @param boolean $requeue
     *
     * @return boolean|Promise\PromiseInterface
     */
    public function reject($deliveryTag, $requeue = true)
    {
        return $this->client->reject($this->id, $deliveryTag, $requeue);
    }

    /**
     * Calls basic.recover-async AMQP method.
     *
     * @param boolean $requeue
     *
     * @return boolean|Promise\PromiseInterface
     */
    public function recoverAsync($requeue = false)
    {
        return $this->client->recoverAsync($this->id, $requeue);
    }

    /**
     * Calls basic.recover AMQP method.
     *
     * @param boolean $requeue
     *
     * @return boolean|Promise\PromiseInterface|Protocol\MethodBasicRecoverOkFrame
     */
    public function recover($requeue = false)
    {
        return $this->client->recover($this->id, $requeue);
    }

    /**
     * Calls basic.nack AMQP method.
     *
     * @param int $deliveryTag
     * @param boolean $multiple
     * @param boolean $requeue
     *
     * @return boolean|Promise\PromiseInterface
     */
    public function nack($deliveryTag = 0, $multiple = false, $requeue = true)
    {
        return $this->client->nack($this->id, $deliveryTag, $multiple, $requeue);
    }

    /**
     * Calls tx.select AMQP method.
     *
     *
     * @return boolean|Promise\PromiseInterface|Protocol\MethodTxSelectOkFrame
     */
    public function txSelect()
    {
        return $this->client->txSelect($this->id);
    }

    /**
     * Calls tx.commit AMQP method.
     *
     *
     * @return boolean|Promise\PromiseInterface|Protocol\MethodTxCommitOkFrame
     */
    public function txCommit()
    {
        return $this->client->txCommit($this->id);
    }

    /**
     * Calls tx.rollback AMQP method.
     *
     *
     * @return boolean|Promise\PromiseInterface|Protocol\MethodTxRollbackOkFrame
     */
    public function txRollback()
    {
        return $this->client->txRollback($this->id);
    }

    /**
     * Calls confirm.select AMQP method.
     *
     * @param boolean $nowait
     *
     * @return boolean|Promise\PromiseInterface|Protocol\MethodConfirmSelectOkFrame
     */
    public function confirmSelect($nowait = false)
    {
        return $this->client->confirmSelect($this->id, $nowait);
    }

}
