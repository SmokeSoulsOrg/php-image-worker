<?php

namespace App\Console\Commands;

use App\Jobs\DownloadAndPublishImage;
use Exception;
use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class ConsumeImageDownloadQueue extends Command
{
    protected $signature = 'consume:image-download';
    protected $description = 'Consume image-download queue and dispatch image download jobs';

    /**
     * @throws Exception
     */
    public function handle(): int
    {
        $connection = new AMQPStreamConnection(
            config('services.rabbitmq.host'),
            config('services.rabbitmq.port'),
            config('services.rabbitmq.user'),
            config('services.rabbitmq.password')
        );

        $channel = $connection->channel();
        $queue = config('services.rabbitmq.image_queue', 'image-download');

        $channel->queue_declare($queue, false, true, false, false);
        $this->info("🟢 Listening on queue: {$queue}");

        $callback = function (AMQPMessage $msg) {
            $payload = json_decode($msg->getBody(), true);

            if (!is_array($payload)) {
                $this->error('❌ Invalid JSON in message');
                $msg->nack();
                return;
            }

            dispatch((new DownloadAndPublishImage($payload))->onQueue('image-events'));

            $msg->ack();
        };

        $channel->basic_consume($queue, '', false, false, false, false, $callback);

        while ($channel->is_consuming()) {
            $channel->wait();
        }

        return 0;
    }
}
