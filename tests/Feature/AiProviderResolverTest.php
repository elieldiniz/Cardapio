<?php

use App\Contracts\VideoGenerationProvider;
use App\Exceptions\UnresolvableVideoProviderException;
use App\Services\Ai\NullVideoGenerationProvider;
use App\Services\Ai\VideoGenerationProviderResolver;

it('resolves the configured provider by slug', function () {
    $resolver = app(VideoGenerationProviderResolver::class);

    $provider = $resolver->resolve('null');

    expect($provider)->toBeInstanceOf(NullVideoGenerationProvider::class)
        ->and($provider)->toBeInstanceOf(VideoGenerationProvider::class);
});

it('throws a clear error when the configured slug has no bound implementation', function () {
    $resolver = app(VideoGenerationProviderResolver::class);

    $resolver->resolve('does-not-exist');
})->throws(UnresolvableVideoProviderException::class, 'does-not-exist');
