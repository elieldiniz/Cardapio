<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Shared behaviour for the slug-keyed lookup tables (statuses, types, roles...).
 */
trait IsLookupTable
{
    /**
     * Resolve a lookup row id by its documented slug.
     */
    public static function idFor(string $slug): int
    {
        return (int) static::query()->where('slug', $slug)->valueOrFail('id');
    }

    public function scopeSlug(Builder $query, string ...$slugs): Builder
    {
        return $query->whereIn('slug', $slugs);
    }
}
