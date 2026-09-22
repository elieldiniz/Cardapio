<?php

namespace App\Contracts;

interface MuxClient
{
    /**
     * Create a Mux asset from a publicly reachable input URL.
     *
     * @return array<string, mixed>
     */
    public function createAsset(string $inputUrl, array $options = []): array;

    /**
     * Create a Mux direct upload, returning the upload URL the client should PUT the file to.
     *
     * @return array<string, mixed>
     */
    public function createDirectUpload(array $options = []): array;

    /**
     * Permanently delete an asset, e.g. content removed by moderation.
     */
    public function deleteAsset(string $assetId): void;

    /**
     * Verify a Mux webhook signature header against the raw request payload.
     */
    public function verifyWebhookSignature(string $payload, string $signatureHeader): bool;
}
