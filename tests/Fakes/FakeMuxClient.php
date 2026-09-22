<?php

namespace Tests\Fakes;

use App\Contracts\MuxClient;

/**
 * In-memory Mux: hands out predictable asset/upload ids and accepts a fixed
 * webhook signature ("valid-signature").
 */
class FakeMuxClient implements MuxClient
{
    /** @var array<int, array{input: string, options: array<string, mixed>}> */
    public array $assets = [];

    /** @var array<int, array<string, mixed>> */
    public array $uploads = [];

    public function createAsset(string $inputUrl, array $options = []): array
    {
        $this->assets[] = ['input' => $inputUrl, 'options' => $options];

        return ['id' => 'asset-'.count($this->assets), 'status' => 'preparing'];
    }

    public function createDirectUpload(array $options = []): array
    {
        $this->uploads[] = $options;
        $id = 'upload-'.count($this->uploads);

        return ['id' => $id, 'url' => "https://storage.mux.test/{$id}", 'status' => 'waiting'];
    }

    public function verifyWebhookSignature(string $payload, string $signatureHeader): bool
    {
        return $signatureHeader === 'valid-signature';
    }
}
