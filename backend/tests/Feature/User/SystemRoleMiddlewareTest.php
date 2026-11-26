<?php

namespace Tests\Feature\User;

use App\Http\Middleware\EnsureUserHasSystemRole;
use App\Models\Tool\Category;
use App\Models\Tool\Tag;
use App\Models\Tool\Tool;
use App\Models\User\CompanyRole;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The `role` middleware (EnsureUserHasSystemRole) lets only users with the given
 * system roles through; the admin-only API routes use `role:admin`.
 */
class SystemRoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Names of the routes only admins may use.
     */
    private const array ADMIN_ROUTES = [
        'api.company-roles.store',
        'api.company-roles.update',
        'api.company-roles.destroy',
        'api.users.index',
        'api.users.store',
        'api.users.destroy',
        'api.users.system-role.update',
        'api.users.two-factor.destroy',
        'api.tools.pending',
        'api.tools.approve',
        'api.tools.reject',
        'api.categories.pending',
        'api.categories.approve',
        'api.categories.reject',
        'api.categories.update',
        'api.categories.destroy',
        'api.tags.all',
        'api.tags.pending',
        'api.tags.approve',
        'api.tags.reject',
        'api.tags.update',
        'api.tags.destroy',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['api', 'auth:sanctum', 'role:admin'])
            ->get('/api/test/admin-only', fn () => 'ok');
        Route::middleware(['api', 'auth:sanctum', 'role:admin,user'])
            ->get('/api/test/admin-or-user', fn () => 'ok');
    }

    public function test_an_admin_passes(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->getJson('/api/test/admin-only')
            ->assertOk();
    }

    public function test_a_user_gets_403(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/test/admin-only')
            ->assertForbidden()
            ->assertJsonPath('message', 'This action is unauthorized.');
    }

    public function test_the_company_owner_who_is_not_admin_gets_403(): void
    {
        $this->actingAs(User::factory()->companyRole('owner')->create())
            ->getJson('/api/test/admin-only')
            ->assertForbidden();
    }

    public function test_a_guest_gets_401(): void
    {
        $this->getJson('/api/test/admin-only')->assertUnauthorized();
    }

    public function test_any_of_several_roles_passes(): void
    {
        $this->actingAs(User::factory()->create())->getJson('/api/test/admin-or-user')->assertOk();
        $this->actingAs(User::factory()->admin()->create())->getJson('/api/test/admin-or-user')->assertOk();
    }

    public function test_every_admin_route_uses_the_middleware(): void
    {
        foreach (self::ADMIN_ROUTES as $name) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertInstanceOf(RoutingRoute::class, $route, "Route {$name} does not exist.");
            $this->assertContains(
                EnsureUserHasSystemRole::class.':admin',
                Route::gatherRouteMiddleware($route),
                "Route {$name} is not limited to admins.",
            );
        }
    }

    public function test_routes_for_every_user_do_not_use_the_middleware(): void
    {
        $protected = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RoutingRoute $route) => in_array(
                EnsureUserHasSystemRole::class.':admin',
                Route::gatherRouteMiddleware($route),
                true,
            ))
            ->map(fn (RoutingRoute $route) => $route->getName())
            ->reject(fn (?string $name) => $name === null)
            ->values()
            ->all();

        $this->assertEqualsCanonicalizing(self::ADMIN_ROUTES, $protected);
    }

    public function test_a_user_gets_403_on_admin_routes_even_for_missing_records(): void
    {
        $this->actingAs(User::factory()->create());

        $this->deleteJson('/api/users/999999')->assertForbidden();
        $this->postJson('/api/tools/999999/approve')->assertForbidden();
        $this->putJson('/api/categories/999999', ['name' => 'x'])->assertForbidden();
        $this->deleteJson('/api/tags/999999')->assertForbidden();
        $this->deleteJson('/api/company-roles/999999')->assertForbidden();
    }

    public function test_a_user_gets_403_on_every_admin_route(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $tool = Tool::factory()->pending()->create();
        $category = Category::factory()->pending()->create();
        $tag = Tag::factory()->pending()->create();
        $companyRole = CompanyRole::where('slug', 'qa')->firstOrFail();

        $this->actingAs($user);

        $this->postJson('/api/company-roles', ['slug' => 'new-role', 'name' => 'Нова'])->assertForbidden();
        $this->putJson("/api/company-roles/{$companyRole->id}", ['name' => 'Друга'])->assertForbidden();
        $this->deleteJson("/api/company-roles/{$companyRole->id}")->assertForbidden();
        $this->getJson('/api/users')->assertForbidden();
        $this->postJson('/api/users', [])->assertForbidden();
        $this->deleteJson("/api/users/{$other->id}")->assertForbidden();
        $this->patchJson("/api/users/{$other->id}/system-role", ['system_role' => 'admin'])->assertForbidden();
        $this->deleteJson("/api/users/{$other->id}/two-factor-authentication")->assertForbidden();
        $this->getJson('/api/tools/pending')->assertForbidden();
        $this->postJson("/api/tools/{$tool->id}/approve")->assertForbidden();
        $this->postJson("/api/tools/{$tool->id}/reject")->assertForbidden();
        $this->getJson('/api/categories/pending')->assertForbidden();
        $this->postJson("/api/categories/{$category->id}/approve")->assertForbidden();
        $this->postJson("/api/categories/{$category->id}/reject")->assertForbidden();
        $this->putJson("/api/categories/{$category->id}", ['name' => 'x'])->assertForbidden();
        $this->deleteJson("/api/categories/{$category->id}")->assertForbidden();
        $this->getJson('/api/tags/all')->assertForbidden();
        $this->getJson('/api/tags/pending')->assertForbidden();
        $this->postJson("/api/tags/{$tag->id}/approve")->assertForbidden();
        $this->postJson("/api/tags/{$tag->id}/reject")->assertForbidden();
        $this->putJson("/api/tags/{$tag->id}", ['name' => 'x'])->assertForbidden();
        $this->deleteJson("/api/tags/{$tag->id}")->assertForbidden();

        $this->assertSame('user', $other->fresh()->system_role->value);
        $this->assertFalse($tool->fresh()->isApproved());
        $this->assertModelExists($companyRole);
    }
}
