<?php

namespace App\Jobs;

use App\Services\ImagePublisher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DownloadAndPublishImage implements ShouldQueue
{
    use Queueable, SerializesModels;

    public array $payload;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function handle(): void
    {
        $url = $this->payload['url'] ?? null;
        if (!$url) {
            Log::error('[Download] Missing URL in payload', ['payload' => $this->payload]);
            return;
        }

        try {
            $response = Http::timeout(10)->get($url);
            if (!$response->successful()) {
                throw new \Exception("Failed to fetch image: HTTP {$response->status()}");
            }

            $filename = md5($url) . '.' . pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
            $relativePath = "pornstar-images/{$filename}";

            Storage::disk('public')->put($relativePath, $response->body());

            app(ImagePublisher::class)->publish([
                'url' => $url,
                'type' => $this->payload['type'] ?? null,
                'local_path' => "storage/{$relativePath}",
            ]);

            Log::info("[Download] Image downloaded and published: storage/{$relativePath}");
        } catch (\Throwable $e) {
            Log::error('[Download] Failed', [
                'url' => $url,
                'type' => $this->payload['type'] ?? null,
                'error' => $e->getMessage()
            ]);
        }
    }
}
