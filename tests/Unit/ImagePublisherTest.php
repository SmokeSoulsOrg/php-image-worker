<?php

namespace Tests\Unit;

use App\Services\ImagePublisher;
use Exception;
use Illuminate\Support\Facades\Config;
use Mockery;
use PhpAmqpLib\Channel\AMQPChannel;
use Tests\TestCase;

class ImagePublisherTest extends TestCase
{
    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * @throws Exception
     */
    public function test_it_publishes_message_to_configured_queue(): void
    {
        $payload = [
            'thumbnail_url_id' => 123,
            'url' => 'https://cdn.example.com/image.jpg',
            'local_path' => 'storage/pornstar-images/image.jpg'
        ];

        Config::set('services.rabbitmq.image_update_queue', 'image-update');

        $mockChannel = Mockery::mock(AMQPChannel::class);
        $mockChannel->shouldReceive('queue_declare')->once()->with(
            'image-update',
            false,
            true,
            false,
            false,
            false,
            [
                'x-dead-letter-exchange'    => ['S', ''],
                'x-dead-letter-routing-key' => ['S', 'image-update-dead'],
            ]
        );

        $mockChannel->shouldReceive('basic_publish')->once()->with(
            Mockery::on(function ($msg) use ($payload) {
                $decoded = json_decode($msg->getBody(), true);
                return $decoded == $payload;
            }),
            '',
            'image-update'
        );

        $publisher = new ImagePublisher($mockChannel);
        $publisher->publish($payload);

        $this->assertTrue(true);
    }
}
