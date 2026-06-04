<?php

namespace App\Services;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use RuntimeException;

class RabbitMQRpcClient
{
    private int $timeout;

    public function __construct(int $timeout = 10)
    {
        $this->timeout = $timeout;
    }

    public function call(string $queue, array $payload): array
    {
        $connection = new AMQPStreamConnection(
            config('rabbitmq.host'),
            config('rabbitmq.port'),
            config('rabbitmq.user'),
            config('rabbitmq.password'),
            config('rabbitmq.vhost', '/'),
        );

        $channel = $connection->channel();

        [$callbackQueue] = $channel->queue_declare('', false, false, true, false);
        $correlationId   = uniqid('', true);

        $message = new AMQPMessage(
            json_encode($payload),
            [
                'content_type'   => 'application/json',
                'correlation_id' => $correlationId,
                'reply_to'       => $callbackQueue,
                'delivery_mode'  => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            ]
        );

        $channel->basic_publish($message, 'edupay', $queue);

        $response = null;
        $channel->basic_consume(
            $callbackQueue, '', false, true, false, false,
            function (AMQPMessage $msg) use ($correlationId, &$response) {
                if ($msg->get('correlation_id') === $correlationId) {
                    $response = json_decode($msg->body, true);
                }
            }
        );

        $deadline = time() + $this->timeout;
        while ($response === null) {
            if (time() >= $deadline) {
                $channel->close();
                $connection->close();
                throw new RuntimeException("RPC timeout waiting for response from queue: {$queue}");
            }
            $channel->wait(null, false, $this->timeout);
        }

        $channel->close();
        $connection->close();

        return $response;
    }
}
