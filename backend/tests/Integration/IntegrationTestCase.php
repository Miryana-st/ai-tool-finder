<?php

namespace Tests\Integration;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Tests\TestCase;

/**
 * Base for the integration tests (phpunit.integration.xml): the application
 * against real MySQL and Redis instances.
 *
 * State is reset around every test: RefreshDatabase migrates the test database
 * once and rolls each test back in a transaction, and the Redis cache database
 * is emptied before and after each test.
 */
abstract class IntegrationTestCase extends TestCase
{
    use RefreshDatabase;

    /**
     * The only database the tests may migrate and empty.
     */
    private const string TEST_DATABASE = 'ai_finder_testing';

    /**
     * The only Redis database index the tests may flush.
     */
    private const string TEST_REDIS_CACHE_DB = '15';

    /**
     * Refuse to run against anything but the test instances, before
     * RefreshDatabase touches the database.
     */
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $config = $app['config'];

        $connection = $config->get('database.default');
        $database = $config->get("database.connections.{$connection}.database");

        if ($connection !== 'mysql' || $database !== self::TEST_DATABASE) {
            throw new RuntimeException(sprintf(
                'Integration tests need the MySQL database "%s", got %s "%s". Run them with -c phpunit.integration.xml.',
                self::TEST_DATABASE,
                $connection,
                $database,
            ));
        }

        // The database of the connection the cache store really uses: that is what Cache::flush() empties.
        $redisConnection = $config->get('cache.stores.redis.connection');
        $redisDatabase = $config->get("database.redis.{$redisConnection}.database");

        if ($config->get('cache.default') !== 'redis' || (string) $redisDatabase !== self::TEST_REDIS_CACHE_DB) {
            throw new RuntimeException(sprintf(
                'Integration tests need the Redis cache database %s, got connection "%s" with database %s. Run them with -c phpunit.integration.xml.',
                self::TEST_REDIS_CACHE_DB,
                $redisConnection,
                var_export($redisDatabase, true),
            ));
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->beforeApplicationDestroyed(fn () => Cache::flush());
    }
}
