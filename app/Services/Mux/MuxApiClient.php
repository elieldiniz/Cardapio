<?php

namespace App\Services\Mux;

use App\Contracts\MuxClient;
use Illuminate\Support\Facades\Http;

class MuxApiClient implements MuxClient
{
    /**
     * MP4 (faststart) renditions the feed plays: 480p first, 720p when the
     * connection allows (Tech Stack — Vídeo no feed; US-1.2).
     */
    public const STATIC_RENDITIONS = [['resolution' => '480p'], ['resolution' => '720p']];

    public function __construct(
        private readonly string $tokenId,
        private readonly string $tokenSecret,
        private readonly string $webhookSecret,
        private readonly string $baseUrl = 'https://api.mux.com',
    ) {}

    public function createAsset(string $inputUrl, array $options = []): array
    {
        $response = $this->client()
            ->post('/video/v1/assets', array_merge([
                'input' => [['url' => $inputUrl]],
                'playback_policy' => ['public'],
                'static_renditions' => self::STATIC_RENDITIONS,
            ], $options))
            ->throw();

        return $response->json('data') ?? [];
    }

    public function createDirectUpload(array $options = []): array
    {
        $response = $this->client()
            ->post('/video/v1/uploads', array_merge([
                'new_asset_settings' => array_merge([
                    'playback_policy' => ['public'],
                    'static_renditions' => self::STATIC_RENDITIONS,
                ], $options['new_asset_settings'] ?? []),
            ], array_diff_key($options, ['new_asset_settings' => true])))
            ->throw();

        return $response->json('data') ?? [];
    }

    public function verifyWebhookSignature(string $payload, string $signatureHeader): bool
    {
        if ($this->webhookSecret === '') {
            return false;
        }

        $parts = [];

        foreach (explode(',', $signatureHeader) as $segment) {
            [$key, $value] = array_pad(explode('=', trim($segment), 2), 2, null);

            if ($key !== null && $value !== null) {
                $parts[$key] = $value;
            }
        }

        if (! isset($parts['t'], $parts['v1'])) {
            return false;
        }

        $expected = hash_hmac('sha256', "{$parts['t']}.{$payload}", $this->webhookSecret);

        return hash_equals($expected, $parts['v1']);
    }

    private function client()
    {
        return Http::baseUrl($this->baseUrl)
            ->withBasicAuth($this->tokenId, $this->tokenSecret)
            ->acceptJson()
            ->asJson();
    }
}
