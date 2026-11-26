<?php

namespace Tests\Feature\Tool;

use App\Models\Tool\Category;
use App\Models\Tool\Tag;
use App\Models\Tool\Tool;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The order of the tool lists and of the pending suggestions.
 */
class ListOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    /**
     * Mark the tool as a favorite of the given number of new users.
     */
    private function favoritedBy(Tool $tool, int $count): Tool
    {
        $tool->favoritedBy()->attach(User::factory()->count($count)->create());

        return $tool;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<string>
     */
    private function listedNames(array $query = []): array
    {
        return $this->actingAs($this->user)
            ->getJson(route('api.tools.index', $query))
            ->assertOk()
            ->json('data.*.name');
    }

    public function test_tools_are_ordered_by_favorites_then_by_name(): void
    {
        $this->favoritedBy(Tool::factory()->create(['name' => 'Bravo']), 1);
        $this->favoritedBy(Tool::factory()->create(['name' => 'Zulu']), 3);
        Tool::factory()->create(['name' => 'Charlie']);
        $this->favoritedBy(Tool::factory()->create(['name' => 'Alpha']), 1);
        Tool::factory()->create(['name' => 'Ален']);

        $this->assertSame(['Zulu', 'Alpha', 'Bravo', 'Charlie', 'Ален'], $this->listedNames());
    }

    public function test_own_tools_are_ordered_by_favorites_then_by_name(): void
    {
        Tool::factory()->for($this->user)->create(['name' => 'Bravo']);
        $this->favoritedBy(Tool::factory()->for($this->user)->create(['name' => 'Charlie']), 2);
        Tool::factory()->for($this->user)->create(['name' => 'Alpha']);
        Tool::factory()->create(['name' => 'Not mine']);

        $this->assertSame(['Charlie', 'Alpha', 'Bravo'], $this->listedNames(['mine' => 1]));
    }

    public function test_the_own_favorites_stay_newest_first(): void
    {
        $older = $this->favoritedBy(Tool::factory()->create(['name' => 'Alpha', 'created_at' => now()->subDay()]), 5);
        $newer = Tool::factory()->create(['name' => 'Bravo']);
        $this->user->favoriteTools()->attach([$older->id, $newer->id]);

        $this->assertSame(['Bravo', 'Alpha'], $this->listedNames(['favorites' => 1]));
    }

    public function test_pending_categories_and_tags_are_ordered_by_name(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (['Zeta', 'Alpha', 'Бета'] as $name) {
            Category::factory()->pending()->create(['name' => $name]);
            Tag::factory()->pending()->create(['name' => $name]);
        }

        foreach (['categories', 'tags'] as $kind) {
            $this->actingAs($admin)
                ->getJson("/api/{$kind}/pending")
                ->assertOk()
                ->assertJsonPath('data.*.name', ['Alpha', 'Zeta', 'Бета']);
        }
    }
}
