<?php

namespace App\Services;

use Exception;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class ImagePublisher
{
    protected string $queue;
    protected AMQPChannel $channel;

    /**
     * @throws Exception
     */
    public function __construct()
    {
        $connection = new AMQPStreamConnection(
            config('services.rabbitmq.host'),
            config('services.rabbitmq.port'),
            config('services.rabbitmq.user'),
            config('services.rabbitmq.password')
        );

        $this->channel = $connection->channel();
        $this->queue = config('services.rabbitmq.image_update_queue', 'image-update');

        $this->channel->queue_declare($this->queue, false, true, false, false);
    }

    public function publish(array $payload): void
    {
        $message = new AMQPMessage(json_encode($payload), [
            'content_type' => 'application/json',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
        ]);

        $this->channel->basic_publish($message, '', $this->queue);
    }
}
