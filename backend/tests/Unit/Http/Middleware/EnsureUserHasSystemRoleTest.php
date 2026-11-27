<?php

namespace Tests\Unit\Http\Middleware;

use App\Enums\User\SystemRole;
use App\Http\Middleware\EnsureUserHasSystemRole;
use App\Models\User\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * The `role` middleware called directly with an in-memory user. The routes
 * that use it are covered by the SystemRoleMiddlewareTest feature test.
 */
class EnsureUserHasSystemRoleTest extends TestCase
{
    private EnsureUserHasSystemRole $middleware;

    private bool $nextCalled;

    protected function setUp(): void
    {
        parent::setUp();

        $this->middleware = new EnsureUserHasSystemRole;
        $this->nextCalled = false;
    }

    private function request(?SystemRole $role): Request
    {
        $user = $role === null ? null : (new User)->forceFill(['system_role' => $role]);

        return Request::create('/')->setUserResolver(fn () => $user);
    }

    private function handle(Request $request, string ...$roles): Response
    {
        return $this->middleware->handle($request, function () {
            $this->nextCalled = true;

            return new Response('passed');
        }, ...$roles);
    }

    private function assertAborts(int $status, Request $request, string ...$roles): void
    {
        try {
            $this->handle($request, ...$roles);
            $this->fail("Expected HTTP {$status}.");
        } catch (HttpException $exception) {
            $this->assertSame($status, $exception->getStatusCode());
        }

        $this->assertFalse($this->nextCalled, 'The request must not reach the next handler.');
    }

    public function test_user_with_the_role_passes_through(): void
    {
        $response = $this->handle($this->request(SystemRole::Admin), 'admin');

        $this->assertTrue($this->nextCalled);
        $this->assertSame('passed', $response->getContent());
    }

    public function test_user_with_any_of_several_roles_passes_through(): void
    {
        $this->handle($this->request(SystemRole::User), 'admin', 'user');

        $this->assertTrue($this->nextCalled);
    }

    public function test_user_without_the_role_is_forbidden(): void
    {
        $this->assertAborts(Response::HTTP_FORBIDDEN, $this->request(SystemRole::User), 'admin');
    }

    public function test_guest_is_unauthenticated(): void
    {
        $this->assertAborts(Response::HTTP_UNAUTHORIZED, $this->request(null), 'admin');
    }

    public function test_no_roles_given_lets_nobody_through(): void
    {
        $this->assertAborts(Response::HTTP_FORBIDDEN, $this->request(SystemRole::Admin));
    }

    public function test_unknown_role_name_is_a_programming_error(): void
    {
        $this->expectException(\ValueError::class);

        $this->handle($this->request(SystemRole::Admin), 'superadmin');
    }
}
