<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Cache for catalog data that is the same for every user and read often:
 * company roles, approved categories with their tool counts and the shared
 * dashboard numbers. Nothing that depends on the user is stored here.
 *
 * Keys carry a version number; flush() raises it, so every cached value is
 * replaced at once on the next read. Old keys simply expire. This works with
 * any cache store (no cache tags needed).
 */
class CatalogCache
{
    private const string VERSION_KEY = 'catalog.version';

    /**
     * A safety net only: changes flush the cache right away.
     */
    private const int TTL_SECONDS = 3600;

    /**
     * Get the cached value, or compute and store it. The value must be plain
     * data (arrays, scalars), not models.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function remember(string $key, Closure $callback): mixed
    {
        $version = (int) Cache::get(self::VERSION_KEY, 0);

        return Cache::remember("catalog.v{$version}.{$key}", self::TTL_SECONDS, $callback);
    }

    /**
     * Drop every cached catalog value once the current transaction commits,
     * so a value computed from uncommitted data is never kept.
     */
    public function flush(): void
    {
        DB::afterCommit(function () {
            // The database store cannot increment a missing key, so create it first.
            Cache::add(self::VERSION_KEY, 0);
            Cache::increment(self::VERSION_KEY);
        });
    }
}
