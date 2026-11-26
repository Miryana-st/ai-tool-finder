<?php

namespace Tests\Feature\User;

use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * PUT /api/user/password (Fortify): users change their own password,
 * e.g. the one an admin chose when creating the account.
 */
class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_change_their_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/user/password', [
                'current_password' => 'password',
                'password' => 'new-secret-password',
                'password_confirmation' => 'new-secret-password',
            ])
            ->assertOk();

        $this->assertTrue(Hash::check('new-secret-password', $user->fresh()->password));
    }

    public function test_current_password_must_be_correct(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/user/password', [
                'current_password' => 'wrong-password',
                'password' => 'new-secret-password',
                'password_confirmation' => 'new-secret-password',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_new_password_must_be_confirmed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/user/password', [
                'current_password' => 'password',
                'password' => 'new-secret-password',
                'password_confirmation' => 'something-else',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_guest_cannot_change_a_password(): void
    {
        $this->putJson('/api/user/password', [
            'current_password' => 'password',
            'password' => 'new-secret-password',
            'password_confirmation' => 'new-secret-password',
        ])->assertUnauthorized();
    }
}
