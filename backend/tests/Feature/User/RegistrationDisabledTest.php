<?php

namespace Tests\Feature\User;

use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The app is for employees only: there is no public registration, only admins
 * create accounts (POST /api/users, see UserCreateTest).
 */
class RegistrationDisabledTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_register_endpoint_does_not_exist(): void
    {
        $this->assertFalse(Route::has('register.store'));

        $this->postJson('/api/register', [
            'name' => 'New User',
            'email' => 'new@ai-finder.test',
            'password' => 'Str0ng-Passw0rd!',
            'password_confirmation' => 'Str0ng-Passw0rd!',
            'company_role' => 'qa',
        ])->assertNotFound();

        $this->assertDatabaseMissing(User::class, ['email' => 'new@ai-finder.test']);
    }
}
