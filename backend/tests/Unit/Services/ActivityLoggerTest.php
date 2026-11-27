<?php

namespace Tests\Unit\Services;

use App\Enums\User\SystemRole;
use App\Services\ActivityLogger;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * How ActivityLogger compares values before and after a change. Writing the
 * entry itself is covered by the Activity feature tests.
 */
class ActivityLoggerTest extends TestCase
{
    private ActivityLogger $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = new ActivityLogger(Request::create('/'), $this->createStub(AuthFactory::class));
    }

    public function test_diff_returns_only_the_changed_fields(): void
    {
        $changes = $this->logger->diff(
            ['name' => 'Old', 'url' => 'https://a.test'],
            ['name' => 'New', 'url' => 'https://a.test'],
        );

        $this->assertSame(['name' => ['old' => 'Old', 'new' => 'New']], $changes);
    }

    public function test_diff_of_equal_values_is_empty(): void
    {
        $values = ['name' => 'Same', 'tags' => ['a', 'b']];

        $this->assertSame([], $this->logger->diff($values, $values));
    }

    public function test_diff_includes_fields_present_on_only_one_side(): void
    {
        $changes = $this->logger->diff(['removed' => 'x'], ['added' => 'y']);

        $this->assertSame([
            'removed' => ['old' => 'x', 'new' => null],
            'added' => ['old' => null, 'new' => 'y'],
        ], $changes);
    }

    public function test_diff_ignores_the_order_of_lists(): void
    {
        $this->assertSame([], $this->logger->diff(['tags' => ['b', 'a']], ['tags' => ['a', 'b']]));
    }

    public function test_diff_reports_changed_lists_sorted(): void
    {
        $changes = $this->logger->diff(['tags' => ['c', 'a']], ['tags' => ['b', 'a']]);

        $this->assertSame(['tags' => ['old' => ['a', 'c'], 'new' => ['a', 'b']]], $changes);
    }

    /**
     * Empty values that mean "nothing" compare equal to a missing value.
     *
     * @return array<string, array{mixed, mixed}>
     */
    public static function equivalentEmptyValues(): array
    {
        return [
            'empty string and null' => ['', null],
            'empty list and null' => [[], null],
            'empty string and empty list' => ['', []],
        ];
    }

    #[DataProvider('equivalentEmptyValues')]
    public function test_diff_treats_empty_values_as_null(mixed $before, mixed $after): void
    {
        $this->assertSame([], $this->logger->diff(['field' => $before], ['field' => $after]));
    }

    public function test_diff_keeps_falsy_values_that_are_not_empty(): void
    {
        $changes = $this->logger->diff(['count' => null, 'active' => null], ['count' => 0, 'active' => false]);

        $this->assertSame([
            'count' => ['old' => null, 'new' => 0],
            'active' => ['old' => null, 'new' => false],
        ], $changes);
    }

    public function test_diff_compares_enums_by_their_value(): void
    {
        $this->assertSame([], $this->logger->diff(['role' => SystemRole::Admin], ['role' => 'admin']));

        $this->assertSame(
            ['role' => ['old' => 'user', 'new' => 'admin']],
            $this->logger->diff(['role' => SystemRole::User], ['role' => SystemRole::Admin]),
        );
    }

    public function test_created_lists_every_filled_value_as_new(): void
    {
        $changes = $this->logger->created(['name' => 'Tool', 'description' => '', 'tags' => []]);

        $this->assertSame(['name' => ['old' => null, 'new' => 'Tool']], $changes);
    }

    public function test_deleted_lists_every_filled_value_as_removed(): void
    {
        $changes = $this->logger->deleted(['name' => 'Tool', 'description' => null, 'tags' => ['b', 'a']]);

        $this->assertSame([
            'name' => ['old' => 'Tool', 'new' => null],
            'tags' => ['old' => ['a', 'b'], 'new' => null],
        ], $changes);
    }
}
