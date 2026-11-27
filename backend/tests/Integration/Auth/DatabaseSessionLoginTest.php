<?php

namespace Tests\Integration\Auth;

use App\Models\User\User;
use Illuminate\Testing\TestResponse;
use Tests\Integration\IntegrationTestCase;

/**
 * The SPA sign-in as the browser does it, with the session stored in the
 * MySQL `sessions` table: only the session cookie carries the login from one
 * request to the next.
 */
class DatabaseSessionLoginTest extends IntegrationTestCase
{
    private const string FRONTEND_ORIGIN = 'http://localhost:3000';

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

    /**
     * The session id the response's cookie carries.
     */
    private function sessionId(TestResponse $response): string
    {
        $cookie = $response->getCookie(config('session.cookie'));
        $this->assertNotNull($cookie, 'The response sets the session cookie.');

        return $cookie->getValue();
    }

    /**
     * A request from the browser with only the given session cookie.
     *
     * In production every request runs in a fresh PHP process, but the test
     * client serves them all from one application. So what it kept from
     * earlier requests is dropped first: the guards with their signed-in user,
     * the session store, and the controllers the routes cache (Fortify's hold
     * the guard they were built with).
     */
    private function asBrowserWithSession(string $sessionId): static
    {
        $this->forgetEarlierRequests();

        // Like the frontend's `credentials: 'include'`; without it JSON requests send no cookies.
        return $this->withHeaders($this->spaHeaders())
            ->withCredentials()
            ->withCookie(config('session.cookie'), $sessionId);
    }

    /**
     * Drop the per-request state the application kept (see asBrowserWithSession).
     */
    private function forgetEarlierRequests(): void
    {
        $this->app['auth']->forgetGuards();
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');

        foreach ($this->app['router']->getRoutes() as $route) {
            $route->flushController();
        }
    }

    public function test_the_session_is_stored_in_mysql_and_carries_the_login(): void
    {
        $user = User::factory()->create(['email' => 'pm@ai-finder.test']);

        $this->withHeaders($this->spaHeaders())->get('/sanctum/csrf-cookie')->assertNoContent();

        $login = $this->withHeaders($this->spaHeaders())
            ->postJson('/api/login', ['email' => 'pm@ai-finder.test', 'password' => 'password'])
            ->assertOk();

        $sessionId = $this->sessionId($login);
        $this->assertDatabaseHas('sessions', ['id' => $sessionId, 'user_id' => $user->id]);

        $this->asBrowserWithSession($sessionId)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.email', 'pm@ai-finder.test');
    }

    public function test_an_unknown_session_is_not_signed_in(): void
    {
        $this->asBrowserWithSession(str_repeat('x', 40))
            ->getJson('/api/user')
            ->assertUnauthorized();
    }

    public function test_logout_ends_the_stored_session(): void
    {
        $user = User::factory()->create(['email' => 'qa@ai-finder.test']);

        $sessionId = $this->sessionId(
            $this->withHeaders($this->spaHeaders())
                ->postJson('/api/login', ['email' => 'qa@ai-finder.test', 'password' => 'password'])
                ->assertOk()
        );

        $this->asBrowserWithSession($sessionId)->postJson('/api/logout')->assertNoContent();

        $this->assertDatabaseMissing('sessions', ['id' => $sessionId, 'user_id' => $user->id]);
        $this->asBrowserWithSession($sessionId)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_deleting_a_user_ends_their_stored_session(): void
    {
        $user = User::factory()->create(['email' => 'designer@ai-finder.test']);

        $sessionId = $this->sessionId(
            $this->withHeaders($this->spaHeaders())
                ->postJson('/api/login', ['email' => 'designer@ai-finder.test', 'password' => 'password'])
                ->assertOk()
        );

        $this->forgetEarlierRequests();
        $this->actingAs(User::factory()->admin()->create())
            ->deleteJson("/api/users/{$user->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('sessions', ['id' => $sessionId]);
        $this->asBrowserWithSession($sessionId)->getJson('/api/user')->assertUnauthorized();
    }
}
