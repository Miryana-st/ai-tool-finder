<?php

namespace Tests\Feature;

use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ApiOnlyBackendTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_url_returns_json_not_found(): void
    {
        $this->get('/')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonStructure(['message']);
    }

    public function test_former_blade_pages_are_not_available(): void
    {
        foreach (['/dashboard', '/login', '/register', '/forgot-password'] as $uri) {
            $this->get($uri)->assertNotFound()->assertHeader('Content-Type', 'application/json');
        }
    }

    public function test_former_blade_dashboard_is_not_available_for_logged_in_users(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertNotFound();
    }

    public function test_auth_endpoints_are_not_available_outside_api_prefix(): void
    {
        $this->postJson('/login', ['email' => 'a@b.test', 'password' => 'x'])->assertNotFound();
        $this->postJson('/logout')->assertNotFound();
    }

    public function test_guest_on_protected_endpoint_gets_json_without_redirect(): void
    {
        // A plain browser request (no Accept: application/json) must not redirect to a login page.
        $this->get('/api/user')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    public function test_there_is_no_forgotten_password_reset(): void
    {
        // Users change the password in their profile; admins set it for them.
        Notification::fake();
        User::factory()->create(['email' => 'reset@ai-finder.test']);

        $this->postJson('/api/forgot-password', ['email' => 'reset@ai-finder.test'])->assertNotFound();
        $this->postJson('/api/reset-password', [
            'token' => 'any',
            'email' => 'reset@ai-finder.test',
            'password' => 'N3w-Str0ng-Passw0rd!',
            'password_confirmation' => 'N3w-Str0ng-Passw0rd!',
        ])->assertNotFound();

        Notification::assertNothingSent();
    }
}
