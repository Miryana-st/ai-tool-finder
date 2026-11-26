<?php

namespace App\Http\Middleware;

use App\Enums\User\SystemRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets the request through only for signed-in users with one of the given
 * system roles, e.g. `role:admin` (several: `role:admin,user`).
 *
 * Only the system role counts: the company role (including `owner`) gives no
 * rights in the application. Use it after `auth:sanctum`. Policies still decide
 * the finer rules, e.g. that admins cannot delete themselves.
 */
class EnsureUserHasSystemRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     * @param  string  ...$roles  SystemRole values
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(Response::HTTP_UNAUTHORIZED, 'Unauthenticated.');
        }

        $allowed = array_map(fn (string $role) => SystemRole::from($role), $roles);

        if (! in_array($user->system_role, $allowed, true)) {
            abort(Response::HTTP_FORBIDDEN, 'This action is unauthorized.');
        }

        return $next($request);
    }
}
