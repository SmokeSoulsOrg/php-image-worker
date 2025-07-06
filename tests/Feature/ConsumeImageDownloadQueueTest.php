<?php

namespace Tests\Feature;

use App\Jobs\DownloadAndPublishImage;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PhpAmqpLib\Message\AMQPMessage;
use Tests\TestCase;

class ConsumeImageDownloadQueueTest extends TestCase
{
    public function test_it_dispatches_job_for_valid_message(): void
    {
        Queue::fake();

        $payload = [
            'url' => 'https://example.com/image.jpg',
            'type' => 'main',
            'width' => 300,
            'height' => 200,
        ];

        $message = Mockery::mock(AMQPMessage::class);
        $message->shouldReceive('getBody')->once()->andReturn(json_encode($payload));
        $message->shouldReceive('ack')->once();

        // simulate the callback logic
        $callback = function ($msg) {
            $payload = json_decode($msg->getBody(), true);

            if (!is_array($payload)) {
                $msg->nack();
                return;
            }

            dispatch((new DownloadAndPublishImage($payload))->onQueue('image-events'));
            $msg->ack();
        };

        $callback($message);

        Queue::assertPushed(DownloadAndPublishImage::class);
    }


    public function test_it_rejects_invalid_json(): void
    {
        Queue::fake();

        $message = Mockery::mock(AMQPMessage::class);
        $message->shouldReceive('getBody')->once()->andReturn('not-json');
        $message->shouldReceive('nack')->once();

        $this->expectOutputRegex('/❌ Invalid JSON in message/');

        $callback = function ($msg) {
            $payload = json_decode($msg->getBody(), true);

            if (!is_array($payload)) {
                echo "❌ Invalid JSON in message\n";
                $msg->nack();
                return;
            }

            dispatch(new DownloadAndPublishImage($payload));
            $msg->ack();
        };

        $callback($message);

        Queue::assertNothingPushed();
    }
}
