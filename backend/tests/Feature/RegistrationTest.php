<?php

namespace Tests\Feature;

use App\Enums\SystemRole;
use App\Models\CompanyRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'New User',
            'email' => 'new@ai-finder.test',
            'password' => 'Str0ng-Passw0rd!',
            'password_confirmation' => 'Str0ng-Passw0rd!',
            'company_role' => 'qa',
            ...$overrides,
        ];
    }

    public function test_user_can_register_with_a_company_role(): void
    {
        $this->postJson('/api/register', $this->payload())->assertCreated();

        $user = User::where('email', 'new@ai-finder.test')->firstOrFail();

        $this->assertSame('qa', $user->companyRole->slug);
        $this->assertSame(SystemRole::User, $user->system_role);
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_can_register_with_a_company_role_added_by_an_admin(): void
    {
        CompanyRole::create(['slug' => 'data-scientist', 'name' => 'Data Scientist']);

        $this->postJson('/api/register', $this->payload(['company_role' => 'data-scientist']))->assertCreated();

        $this->assertSame('data-scientist', User::where('email', 'new@ai-finder.test')->firstOrFail()->companyRole->slug);
    }

    public function test_registering_user_cannot_make_themselves_admin(): void
    {
        $this->postJson('/api/register', $this->payload(['system_role' => 'admin']))->assertCreated();

        $this->assertSame(SystemRole::User, User::where('email', 'new@ai-finder.test')->firstOrFail()->system_role);
    }

    public function test_company_role_is_required(): void
    {
        $payload = $this->payload();
        unset($payload['company_role']);

        $this->postJson('/api/register', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('company_role');

        $this->assertDatabaseMissing('users', ['email' => 'new@ai-finder.test']);
    }

    public function test_user_cannot_register_as_owner(): void
    {
        $this->postJson('/api/register', $this->payload(['company_role' => 'owner']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('company_role');

        $this->assertDatabaseMissing('users', ['email' => 'new@ai-finder.test']);
    }

    public function test_user_cannot_register_with_an_unknown_company_role(): void
    {
        $this->postJson('/api/register', $this->payload(['company_role' => 'ghost']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('company_role');

        $this->assertDatabaseMissing('users', ['email' => 'new@ai-finder.test']);
    }
}
