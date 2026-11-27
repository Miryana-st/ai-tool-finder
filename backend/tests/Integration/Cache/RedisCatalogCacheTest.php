<?php

namespace Tests\Integration\Cache;

use App\Models\Tool\Category;
use App\Models\User\CompanyRole;
use App\Models\User\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use RuntimeException;
use Tests\Integration\IntegrationTestCase;

/**
 * CatalogCache against a real Redis server: what is stored there, for how
 * long, and that every change (and only a committed one) replaces it.
 */
class RedisCatalogCacheTest extends IntegrationTestCase
{
    /**
     * The keys in the Redis cache database, without the connection and cache prefixes.
     *
     * @return list<string>
     */
    private function cachedKeys(): array
    {
        $prefix = config('database.redis.options.prefix').Cache::getPrefix();

        return collect(Redis::connection('cache')->keys('*'))
            ->map(fn (string $key) => str_replace($prefix, '', $key))
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Seconds until the cached key expires in Redis.
     */
    private function secondsToLive(string $key): int
    {
        return Redis::connection('cache')->ttl(Cache::getPrefix().$key);
    }

    public function test_a_read_stores_the_catalog_in_redis_with_an_expiry(): void
    {
        $this->assertSame([], $this->cachedKeys());

        $this->getJson('/api/company-roles')->assertOk();

        $this->assertSame(['catalog.v0.company-roles'], $this->cachedKeys());

        $ttl = $this->secondsToLive('catalog.v0.company-roles');
        $this->assertGreaterThan(3500, $ttl);
        $this->assertLessThanOrEqual(3600, $ttl);
    }

    public function test_the_second_read_comes_from_redis(): void
    {
        $this->getJson('/api/company-roles')->assertOk()->assertJsonFragment(['slug' => 'qa', 'name' => 'QA']);

        // Changed behind the application's back (no model events): the cached answer stays.
        DB::table('company_roles')->where('slug', 'qa')->update(['name' => 'Quality']);

        $this->getJson('/api/company-roles')->assertOk()->assertJsonFragment(['slug' => 'qa', 'name' => 'QA']);
    }

    public function test_a_change_raises_the_version_in_redis_and_the_next_read_is_fresh(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $this->getJson('/api/company-roles')->assertOk();
        $this->assertNull(Cache::get('catalog.version'));

        $roleId = CompanyRole::where('slug', 'qa')->value('id');
        $this->putJson("/api/company-roles/{$roleId}", ['name' => 'Quality'])->assertOk();

        $this->assertSame(1, (int) Cache::get('catalog.version'));
        $this->getJson('/api/company-roles')->assertOk()->assertJsonFragment(['slug' => 'qa', 'name' => 'Quality']);
        $this->assertContains('catalog.v1.company-roles', $this->cachedKeys());
    }

    public function test_every_committed_change_raises_the_version(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $category = Category::factory()->create();
        $suggestion = Category::factory()->pending()->create();

        $versions = [];

        $this->putJson("/api/categories/{$category->id}", ['name' => 'Ново име'])->assertOk();
        $versions[] = (int) Cache::get('catalog.version');

        $this->postJson("/api/categories/{$suggestion->id}/approve")->assertOk();
        $versions[] = (int) Cache::get('catalog.version');

        $this->postJson('/api/company-roles', ['slug' => 'data', 'name' => 'Data'])->assertCreated();
        $versions[] = (int) Cache::get('catalog.version');

        $this->assertGreaterThan(0, $versions[0]);
        $this->assertGreaterThan($versions[0], $versions[1]);
        $this->assertGreaterThan($versions[1], $versions[2]);
    }

    public function test_a_change_that_is_rolled_back_keeps_the_cache(): void
    {
        $user = User::factory()->companyRole('qa')->create();
        $this->actingAs($user);
        $category = Category::factory()->create();

        $this->getJson('/api/categories')->assertOk();
        $version = Cache::get('catalog.version');

        DB::listen(function (QueryExecuted $query) {
            if (preg_match('/^insert into [`"]?company_role_tool[`"]?/', $query->sql)) {
                throw new RuntimeException('Simulated failure');
            }
        });

        $this->postJson('/api/tools', [
            'name' => 'Claude',
            'url' => 'https://claude.ai',
            'documentation_url' => 'https://docs.claude.com',
            'description' => 'An AI assistant.',
            'how_to_use' => 'Ask it a question.',
            'company_roles' => [CompanyRole::where('slug', 'qa')->value('id')],
            'categories' => [$category->id],
            'tags' => [],
        ])->assertStatus(500);

        $this->assertSame($version, Cache::get('catalog.version'), 'Nothing was committed, so nothing is flushed.');
        $this->assertDatabaseEmpty('tools');
    }

    public function test_the_tests_use_their_own_redis_database(): void
    {
        $this->assertSame('15', (string) config('database.redis.cache.database'));
        $this->assertSame(15, Redis::connection('cache')->getDbNum());
    }
}
