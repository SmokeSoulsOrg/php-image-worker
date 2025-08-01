<?php

namespace Tests\Unit;

use App\Jobs\DownloadAndPublishImage;
use App\Services\ImagePublisher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use Mockery\MockInterface;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class DownloadAndPublishImageTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_logs_error_if_url_is_missing(): void
    {
        /** @var MockInterface|LoggerInterface $logSpy */
        $logSpy = Mockery::spy(LoggerInterface::class);
        Log::swap($logSpy); // ✅ Ensure facade calls are intercepted

        $job = new DownloadAndPublishImage(['type' => 'main']);
        $job->handle();

        $logSpy->shouldHaveReceived('error')
            ->withArgs(function ($message, $context) {
                return $message === '[Download] Missing URL in payload'
                    && is_array($context)
                    && array_key_exists('payload', $context);
            });

        $this->assertTrue(true); // ✅ Prevents risky test warning
    }

    public function test_it_logs_error_on_failed_http_request(): void
    {
        /** @var MockInterface|LoggerInterface $logSpy */
        $logSpy = Mockery::spy(LoggerInterface::class);
        Log::swap($logSpy);

        Http::fake([
            '*' => Http::response('Not Found', 404),
        ]);

        $job = new DownloadAndPublishImage(['url' => 'https://fake.test/image.jpg']);
        $job->handle();

        $logSpy->shouldHaveReceived('error')
            ->withArgs(function ($message, $context) {
                return $message === '[Download] Failed'
                    && isset($context['url'], $context['error']);
            });

        $this->assertTrue(true); // ✅ Prevents risky test warning
    }

    public function test_it_downloads_and_dispatches_image_publish(): void
    {
        Storage::fake('public');

        $url = 'https://fake.test/image.jpg';
        $imageContent = 'fake image content';

        Http::fake([
            $url => Http::response($imageContent, 200),
        ]);

        /** @var MockInterface|ImagePublisher $publisher */
        $publisher = Mockery::mock(ImagePublisher::class);
        $publisher->shouldReceive('publish')
            ->once()
            ->with(Mockery::on(function ($payload) use ($url) {
                return Str::startsWith($payload['local_path'], 'storage/pornstar-images/')
                    && $payload['url'] === $url;
            }));
        $this->app->instance(ImagePublisher::class, $publisher);

        /** @var MockInterface|LoggerInterface $logSpy */
        $logSpy = Mockery::spy(LoggerInterface::class);
        Log::swap($logSpy);

        $job = new DownloadAndPublishImage(['url' => $url]);
        $job->handle();

        $filename = md5($url) . '.jpg';
        $relativePath = "pornstar-images/{$filename}";

        Storage::disk('public')->assertExists($relativePath);

        $logSpy->shouldHaveReceived('info')
            ->withArgs(function ($message) use ($filename) {
                return str_contains($message, "storage/pornstar-images/{$filename}");
            });
    }
}
