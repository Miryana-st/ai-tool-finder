<?php

namespace Tests\Feature\User;

use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Two-factor authentication with an authenticator app (Fortify). Users turn it
 * on themselves from the profile; it is active only once confirmed with a code.
 */
class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private const string SECRET = 'JBSWY3DPEHPK3PXP';

    /**
     * The current authenticator app code for the secret.
     */
    private function currentCode(string $secret = self::SECRET): string
    {
        return app(Google2FA::class)->getCurrentOtp($secret);
    }

    /**
     * Log in with the password only, as the login form does first.
     */
    private function postLogin(User $user): TestResponse
    {
        return $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password']);
    }

    public function test_login_without_two_factor_signs_the_user_in(): void
    {
        $user = User::factory()->create();

        $this->postLogin($user)->assertOk()->assertJson(['two_factor' => false]);

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_with_two_factor_waits_for_a_code(): void
    {
        $user = User::factory()->withTwoFactor(self::SECRET)->create();

        $this->postLogin($user)->assertOk()->assertJson(['two_factor' => true]);

        $this->assertGuest();
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_a_valid_code_completes_the_login(): void
    {
        $user = User::factory()->withTwoFactor(self::SECRET)->create();

        $this->postLogin($user);

        $this->postJson('/api/two-factor-challenge', ['code' => $this->currentCode()])
            ->assertNoContent();

        $this->assertAuthenticatedAs($user);
        $this->getJson('/api/user')->assertOk()->assertJsonPath('data.two_factor_enabled', true);
    }

    public function test_a_wrong_code_is_refused(): void
    {
        $user = User::factory()->withTwoFactor(self::SECRET)->create();

        $this->postLogin($user);

        $this->postJson('/api/two-factor-challenge', ['code' => '000000'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $this->assertGuest();
    }

    public function test_the_challenge_needs_a_password_login_first(): void
    {
        User::factory()->withTwoFactor(self::SECRET)->create();

        $this->postJson('/api/two-factor-challenge', ['code' => $this->currentCode()])
            ->assertUnprocessable();

        $this->assertGuest();
    }

    public function test_a_recovery_code_completes_the_login_once(): void
    {
        $user = User::factory()->withTwoFactor(self::SECRET, ['recovery-code-1', 'recovery-code-2'])->create();

        $this->postLogin($user);
        $this->postJson('/api/two-factor-challenge', ['recovery_code' => 'recovery-code-1'])->assertNoContent();

        $this->assertAuthenticatedAs($user);
        $this->assertNotContains('recovery-code-1', $user->fresh()->recoveryCodes());
        $this->assertContains('recovery-code-2', $user->fresh()->recoveryCodes());

        $this->postJson('/api/logout')->assertNoContent();
        $this->postLogin($user);

        $this->postJson('/api/two-factor-challenge', ['recovery_code' => 'recovery-code-1'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('recovery_code');

        $this->assertGuest();
    }

    public function test_the_settings_need_a_confirmed_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/user/two-factor-authentication')->assertStatus(423);

        $this->assertNull($user->fresh()->two_factor_secret);
    }

    public function test_a_wrong_password_is_not_confirmed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/user/confirm-password', ['password' => 'wrong-password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->postJson('/api/user/two-factor-authentication')->assertStatus(423);
    }

    public function test_user_turns_on_two_factor_and_confirms_it_with_a_code(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/user/confirm-password', ['password' => 'password'])
            ->assertCreated();

        $this->postJson('/api/user/two-factor-authentication')->assertOk();

        $user->refresh();
        $this->assertNotNull($user->two_factor_secret);
        $this->assertFalse($user->hasEnabledTwoFactorAuthentication());

        $this->getJson('/api/user/two-factor-qr-code')->assertOk()->assertJsonStructure(['svg', 'url']);
        $secret = $this->getJson('/api/user/two-factor-secret-key')->assertOk()->json('secretKey');

        $this->getJson('/api/user')->assertJsonPath('data.two_factor_enabled', false);

        $this->postJson('/api/user/confirmed-two-factor-authentication', ['code' => '000000'])
            ->assertUnprocessable();

        $this->postJson('/api/user/confirmed-two-factor-authentication', ['code' => $this->currentCode($secret)])
            ->assertOk();

        $this->assertTrue($user->fresh()->hasEnabledTwoFactorAuthentication());
        $this->getJson('/api/user')->assertJsonPath('data.two_factor_enabled', true);
        $this->getJson('/api/user/two-factor-recovery-codes')->assertOk()->assertJsonCount(8);
    }

    public function test_an_unconfirmed_setup_does_not_ask_for_a_code_at_login(): void
    {
        $user = User::factory()->create([
            'two_factor_secret' => encrypt(app(TwoFactorAuthenticationProvider::class)->generateSecretKey()),
        ]);

        $this->postLogin($user)->assertOk()->assertJson(['two_factor' => false]);

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_regenerates_recovery_codes(): void
    {
        $user = User::factory()->withTwoFactor(self::SECRET, ['old-code'])->create();

        $this->actingAs($user)->postJson('/api/user/confirm-password', ['password' => 'password']);
        $this->postJson('/api/user/two-factor-recovery-codes')->assertOk();

        $codes = $user->fresh()->recoveryCodes();
        $this->assertCount(8, $codes);
        $this->assertNotContains('old-code', $codes);
    }

    public function test_user_turns_off_two_factor(): void
    {
        $user = User::factory()->withTwoFactor(self::SECRET)->create();

        $this->actingAs($user)->postJson('/api/user/confirm-password', ['password' => 'password']);
        $this->deleteJson('/api/user/two-factor-authentication')->assertOk();

        $user->refresh();
        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_recovery_codes);
        $this->assertNull($user->two_factor_confirmed_at);
    }

    public function test_the_secret_and_recovery_codes_are_never_in_the_user_json(): void
    {
        $user = User::factory()->withTwoFactor(self::SECRET)->create();

        $response = $this->actingAs($user)->getJson('/api/user')->assertOk();

        $this->assertArrayNotHasKey('two_factor_secret', $response->json('data'));
        $this->assertArrayNotHasKey('two_factor_recovery_codes', $response->json('data'));
        $this->assertArrayNotHasKey('two_factor_secret', $user->toArray());
        $this->assertArrayNotHasKey('two_factor_recovery_codes', $user->toArray());
    }

    public function test_guest_gets_401_on_the_settings(): void
    {
        $this->postJson('/api/user/two-factor-authentication')->assertUnauthorized();
        $this->deleteJson('/api/user/two-factor-authentication')->assertUnauthorized();
        $this->getJson('/api/user/two-factor-qr-code')->assertUnauthorized();
        $this->getJson('/api/user/two-factor-recovery-codes')->assertUnauthorized();
    }
}
