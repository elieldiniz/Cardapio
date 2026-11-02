<?php

/**
 * The "public" disk (photos, logos, covers, parked AI clips) stays a local folder by default and can be
 * moved to a bucket by environment variables only, for hosts that wipe the server folder on each deploy.
 */
function filesystemsConfigWith(?string $driver): array
{
    foreach (['_ENV', '_SERVER'] as $bag) {
        if ($driver === null) {
            unset($GLOBALS[$bag]['PUBLIC_DISK_DRIVER']);
        } else {
            $GLOBALS[$bag]['PUBLIC_DISK_DRIVER'] = $driver;
        }
    }

    return require config_path('filesystems.php');
}

afterEach(fn () => filesystemsConfigWith(null));

it('keeps the public disk as a local folder unless asked otherwise', function () {
    $disk = filesystemsConfigWith(null)['disks']['public'];

    expect($disk['driver'])->toBe('local')->and($disk['root'])->toBe(storage_path('app/public'));
});

it('stores the public disk in the bucket when PUBLIC_DISK_DRIVER is s3, keeping files public', function () {
    $disk = filesystemsConfigWith('s3')['disks']['public'];

    expect($disk['driver'])->toBe('s3')->and($disk['visibility'])->toBe('public')->and($disk)->toHaveKeys(['bucket', 'region', 'url', 'endpoint']);
});
