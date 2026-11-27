<?php

namespace Tests\Unit\Services;

use App\Services\CatalogCache;
use Closure;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * CatalogCache on the in-memory array cache store. The database is mocked:
 * the "after commit" callbacks are collected and run by commit().
 */
class CatalogCacheTest extends TestCase
{
    private CatalogCache $cache;

    /** @var list<Closure> */
    private array $afterCommit = [];

    protected function setUp(): void
    {
        parent::setUp();

        DB::partialMock()->shouldReceive('afterCommit')->andReturnUsing(function (Closure $callback) {
            $this->afterCommit[] = $callback;
        });

        $this->cache = new CatalogCache;
    }

    private function commit(): void
    {
        foreach ($this->afterCommit as $callback) {
            $callback();
        }

        $this->afterCommit = [];
    }

    /**
     * A callback that returns how many times it has been called.
     */
    private function counter(): Closure
    {
        $calls = 0;

        return function () use (&$calls) {
            return ++$calls;
        };
    }

    public function test_remember_computes_the_value_only_once(): void
    {
        $compute = $this->counter();

        $this->assertSame(1, $this->cache->remember('roles', $compute));
        $this->assertSame(1, $this->cache->remember('roles', $compute));
    }

    public function test_keys_are_cached_separately(): void
    {
        $this->cache->remember('roles', fn () => ['backend']);

        $this->assertSame(['ai'], $this->cache->remember('categories', fn () => ['ai']));
    }

    public function test_flush_recomputes_every_value_after_commit(): void
    {
        $roles = $this->counter();
        $categories = $this->counter();
        $this->cache->remember('roles', $roles);
        $this->cache->remember('categories', $categories);

        $this->cache->flush();
        $this->commit();

        $this->assertSame(2, $this->cache->remember('roles', $roles));
        $this->assertSame(2, $this->cache->remember('categories', $categories));
    }

    public function test_flush_keeps_the_cached_value_until_the_transaction_commits(): void
    {
        $compute = $this->counter();
        $this->cache->remember('roles', $compute);

        $this->cache->flush();

        $this->assertSame(1, $this->cache->remember('roles', $compute));
    }

    public function test_repeated_flushes_keep_invalidating(): void
    {
        $compute = $this->counter();

        foreach ([1, 2, 3] as $expected) {
            $this->assertSame($expected, $this->cache->remember('roles', $compute));
            $this->cache->flush();
            $this->commit();
        }
    }
}
