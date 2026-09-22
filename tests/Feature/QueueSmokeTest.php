<?php

use App\Jobs\SmokeTestJob;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

it('runs a dispatched job to completion under queue:work on the database connection', function () {
    config(['queue.default' => 'database']);

    $cacheKey = 'smoke-test-job-'.uniqid();

    SmokeTestJob::dispatch($cacheKey);

    expect(Cache::get($cacheKey))->toBeNull();

    Artisan::call('queue:work', [
        'connection' => 'database',
        '--once' => true,
        '--stop-when-empty' => true,
    ]);

    expect(Cache::get($cacheKey))->toBeTrue();
});
