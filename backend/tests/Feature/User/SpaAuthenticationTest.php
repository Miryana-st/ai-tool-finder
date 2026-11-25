<?php

namespace Tests\Feature\User;

use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SpaAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private const FRONTEND_ORIGIN = 'http://localhost:3000';

    /**
     * Headers a browser sends from the Next.js frontend.
     *
     * @return array<string, string>
     */
    private function spaHeaders(): array
    {
        return [
            'Origin' => self::FRONTEND_ORIGIN,
            'Referer' => self::FRONTEND_ORIGIN.'/',
            'Accept' => 'application/json',
        ];
    }

    public function test_csrf_cookie_endpoint_sets_xsrf_cookie_with_cors_headers(): void
    {
        $response = $this->withHeaders($this->spaHeaders())->get('/sanctum/csrf-cookie');

        $response->assertNoContent();
        $response->assertCookie('XSRF-TOKEN');
        $response->assertHeader('Access-Control-Allow-Origin', self::FRONTEND_ORIGIN);
        $response->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public function test_cors_rejects_unknown_origins(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'http://evil.test',
            'Accept' => 'application/json',
        ])->get('/sanctum/csrf-cookie');

        $this->assertNotSame('http://evil.test', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertNotSame('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_login_returns_json_and_user_endpoint_returns_authenticated_user(): void
    {
        $user = User::factory()->companyRole('designer')->create([
            'email' => 'designer@ai-finder.test',
        ]);

        $this->withHeaders($this->spaHeaders())
            ->postJson('/api/login', [
                'email' => 'designer@ai-finder.test',
                'password' => 'password',
            ])
            ->assertOk()
            ->assertJson(['two_factor' => false]);

        $this->assertAuthenticatedAs($user);

        $this->withHeaders($this->spaHeaders())
            ->getJson('/api/user')
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => 'designer@ai-finder.test',
                    'system_role' => 'user',
                    'system_role_label' => 'Потребител',
                    'company_role' => 'designer',
                    'company_role_label' => 'Дизайнер',
                ],
            ]);
    }

    public function test_user_endpoint_does_not_expose_sensitive_attributes(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/user')->assertOk();

        $response->assertJsonMissingPath('data.password');
        $response->assertJsonMissingPath('data.remember_token');
    }

    public function test_login_with_invalid_credentials_returns_validation_error(): void
    {
        User::factory()->create(['email' => 'qa@ai-finder.test']);

        $this->withHeaders($this->spaHeaders())
            ->postJson('/api/login', [
                'email' => 'qa@ai-finder.test',
                'password' => 'wrong-password',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertGuest();
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->withHeaders($this->spaHeaders())
            ->postJson('/api/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_user_endpoint_requires_authentication(): void
    {
        $this->withHeaders($this->spaHeaders())
            ->getJson('/api/user')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    public function test_logout_returns_no_content_and_ends_the_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withHeaders($this->spaHeaders())
            ->postJson('/api/logout')
            ->assertNoContent();

        $this->assertGuest('web');
    }

    public function test_login_is_rate_limited_after_five_attempts(): void
    {
        User::factory()->create(['email' => 'pm@ai-finder.test']);

        $credentials = ['email' => 'pm@ai-finder.test', 'password' => 'wrong-password'];

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->withHeaders($this->spaHeaders())
                ->postJson('/api/login', $credentials)
                ->assertUnprocessable();
        }

        $this->withHeaders($this->spaHeaders())
            ->postJson('/api/login', $credentials)
            ->assertTooManyRequests();

        RateLimiter::clear('pm@ai-finder.test|127.0.0.1');
    }
}
