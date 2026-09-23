<?php

namespace App\Services\Ai;

use App\Contracts\VideoGenerationProvider;
use App\Exceptions\UnresolvableVideoProviderException;
use Illuminate\Contracts\Container\Container;

class VideoGenerationProviderResolver
{
    public function __construct(private readonly Container $container) {}

    /**
     * Resolve the concrete provider implementation bound to the given config slug.
     */
    public function resolve(string $slug): VideoGenerationProvider
    {
        $class = config("ai.providers.{$slug}");

        if (! is_string($class) || ! is_a($class, VideoGenerationProvider::class, true)) {
            throw UnresolvableVideoProviderException::forSlug($slug);
        }

        return $this->container->make($class);
    }

    /**
     * Resolve the provider configured as the application default.
     */
    public function resolveDefault(): VideoGenerationProvider
    {
        return $this->resolve((string) config('ai.default'));
    }
}
