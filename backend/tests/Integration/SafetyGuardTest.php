<?php

namespace Tests\Integration;

use RuntimeException;

/**
 * IntegrationTestCase refuses to start against anything but the test
 * instances, because the tests empty the database and the Redis cache.
 */
class SafetyGuardTest extends IntegrationTestCase
{
    /**
     * Build a fresh application with an environment variable changed, then put it back.
     */
    private function createApplicationWith(string $name, string $value): void
    {
        $previous = [getenv($name), $_ENV[$name] ?? null, $_SERVER[$name] ?? null];
        putenv("{$name}={$value}");
        $_ENV[$name] = $_SERVER[$name] = $value;

        try {
            $this->createApplication();
        } finally {
            $previous[0] === false ? putenv($name) : putenv("{$name}={$previous[0]}");
            $previous[1] === null ? $_ENV = array_diff_key($_ENV, [$name => true]) : $_ENV[$name] = $previous[1];
            $previous[2] === null ? $_SERVER = array_diff_key($_SERVER, [$name => true]) : $_SERVER[$name] = $previous[2];
        }
    }

    public function test_a_cache_connection_on_another_redis_database_is_refused(): void
    {
        // The `default` connection uses REDIS_DB (0), not the test database 15.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Redis cache database 15');

        $this->createApplicationWith('REDIS_CACHE_CONNECTION', 'default');
    }

    public function test_another_mysql_database_is_refused(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MySQL database "ai_finder_testing"');

        $this->createApplicationWith('DB_DATABASE', 'ai_finder');
    }

    public function test_the_test_instances_are_accepted(): void
    {
        $this->assertSame('ai_finder_testing', config('database.connections.mysql.database'));
        $this->assertSame('cache', config('cache.stores.redis.connection'));
    }
}
