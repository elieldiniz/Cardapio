<?php

use App\Contracts\MuxClient;
use App\Services\Mux\MuxApiClient;
use Illuminate\Support\Facades\Http;

it('creates an asset with the given input url', function () {
    Http::fake([
        'api.mux.com/video/v1/assets' => Http::response([
            'data' => [
                'id' => 'asset-123',
                'status' => 'preparing',
                'playback_ids' => [['id' => 'playback-123', 'policy' => 'public']],
            ],
        ], 201),
    ]);

    $client = app(MuxClient::class);

    $asset = $client->createAsset('https://example.com/video.mp4');

    expect($asset)->toMatchArray(['id' => 'asset-123', 'status' => 'preparing']);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.mux.com/video/v1/assets'
            && $request['input'][0]['url'] === 'https://example.com/video.mp4';
    });
});

it('verifies a valid webhook signature and rejects an invalid one', function () {
    $secret = 'test-webhook-secret';
    $payload = json_encode(['type' => 'video.asset.ready']);
    $timestamp = (string) time();
    $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

    $client = new MuxApiClient(
        tokenId: 'token-id',
        tokenSecret: 'token-secret',
        webhookSecret: $secret,
    );

    expect($client->verifyWebhookSignature($payload, "t={$timestamp},v1={$signature}"))->toBeTrue();

    expect($client->verifyWebhookSignature($payload, "t={$timestamp},v1=deadbeef"))->toBeFalse();
});
