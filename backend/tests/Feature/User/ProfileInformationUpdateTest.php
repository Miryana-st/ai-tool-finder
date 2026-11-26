<?php

namespace Tests\Feature\User;

use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PUT /api/user/profile-information (Fortify): users change their own name and email
 * from the profile page.
 */
class ProfileInformationUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_change_their_name_and_email(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/user/profile-information', [
                'name' => 'Ново Име',
                'email' => 'new@example.com',
            ])
            ->assertOk();

        $user->refresh();
        $this->assertSame('Ново Име', $user->name);
        $this->assertSame('new@example.com', $user->email);
    }

    public function test_user_can_keep_their_own_email(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/user/profile-information', [
                'name' => 'Ново Име',
                'email' => $user->email,
            ])
            ->assertOk();

        $this->assertSame('Ново Име', $user->fresh()->name);
    }

    public function test_email_of_another_user_is_rejected(): void
    {
        $other = User::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/user/profile-information', [
                'name' => $user->name,
                'email' => $other->email,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_name_and_email_are_required(): void
    {
        $this->actingAs(User::factory()->create())
            ->putJson('/api/user/profile-information', ['name' => '', 'email' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email']);
    }

    public function test_guest_cannot_change_profile_information(): void
    {
        $this->putJson('/api/user/profile-information', [
            'name' => 'Ново Име',
            'email' => 'new@example.com',
        ])->assertUnauthorized();
    }
}
