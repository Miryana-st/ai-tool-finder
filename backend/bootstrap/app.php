<?php

use App\Http\Middleware\EnsureUserHasSystemRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        // `role:admin` on routes for certain system roles only (see EnsureUserHasSystemRole).
        $middleware->alias([
            'role' => EnsureUserHasSystemRole::class,
        ]);

        // Check the role before route model binding, so other users get 403 and
        // cannot tell which ids exist from a 404.
        $middleware->prependToPriorityList(SubstituteBindings::class, EnsureUserHasSystemRole::class);

        // No login page on the backend: unauthenticated requests get a 401 JSON response.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API-only backend: every error (including unknown URLs) is rendered as JSON.
        $exceptions->shouldRenderJsonWhen(fn () => true);
    })->create();
