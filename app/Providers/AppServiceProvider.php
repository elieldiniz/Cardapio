<?php

namespace App\Providers;

use App\Contracts\MuxClient;
use App\Contracts\VideoGenerationProvider;
use App\Services\Ai\VideoGenerationProviderResolver;
use App\Services\Mux\MuxApiClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(MuxClient::class, function () {
            return new MuxApiClient(
                tokenId: (string) config('services.mux.token_id'),
                tokenSecret: (string) config('services.mux.token_secret'),
                webhookSecret: (string) config('services.mux.webhook_secret'),
                baseUrl: (string) config('services.mux.base_url'),
            );
        });

        $this->app->singleton(VideoGenerationProviderResolver::class);

        $this->app->bind(VideoGenerationProvider::class, function ($app) {
            return $app->make(VideoGenerationProviderResolver::class)->resolveDefault();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
