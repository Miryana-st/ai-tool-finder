<?php

use App\Http\Controllers\Activity\ActivityLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Tool\CategoryController;
use App\Http\Controllers\Tool\FavoriteToolController;
use App\Http\Controllers\Tool\TagController;
use App\Http\Controllers\Tool\ToolController;
use App\Http\Controllers\Tool\ToolImageController;
use App\Http\Controllers\User\CompanyRoleController;
use App\Http\Controllers\User\UserController;
use App\Http\Controllers\User\UserSystemRoleController;
use App\Http\Controllers\User\UserTwoFactorController;
use App\Http\Resources\User\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/company-roles', [CompanyRoleController::class, 'index'])
    ->name('api.company-roles.index');

// `role:admin` groups the admin-only routes (see EnsureUserHasSystemRole); the
// policies still check the finer rules, e.g. that admins cannot delete themselves.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $request) => new UserResource($request->user()))
        ->name('api.user');

    Route::get('/dashboard', DashboardController::class)
        ->name('api.dashboard');

    Route::middleware('role:admin')->group(function () {
        Route::apiResource('company-roles', CompanyRoleController::class)
            ->only(['store', 'update', 'destroy'])
            ->names('api.company-roles');

        Route::get('/users', [UserController::class, 'index'])
            ->name('api.users.index');

        Route::post('/users', [UserController::class, 'store'])
            ->name('api.users.store');

        Route::get('/users/{user}', [UserController::class, 'show'])
            ->name('api.users.show');

        Route::put('/users/{user}', [UserController::class, 'update'])
            ->name('api.users.update');

        Route::delete('/users/{user}', [UserController::class, 'destroy'])
            ->name('api.users.destroy');

        Route::patch('/users/{user}/system-role', UserSystemRoleController::class)
            ->name('api.users.system-role.update');

        Route::delete('/users/{user}/two-factor-authentication', [UserTwoFactorController::class, 'destroy'])
            ->name('api.users.two-factor.destroy');

        Route::get('/activity-logs', [ActivityLogController::class, 'index'])
            ->name('api.activity-logs.index');

        Route::get('/activity-logs/events', [ActivityLogController::class, 'events'])
            ->name('api.activity-logs.events');
    });

    Route::get('/tools', [ToolController::class, 'index'])->name('api.tools.index');
    Route::post('/tools', [ToolController::class, 'store'])->name('api.tools.store');

    // Before /tools/{tool:slug}, so "pending" is not read as a slug.
    Route::middleware('role:admin')->group(function () {
        Route::get('/tools/pending', [ToolController::class, 'pending'])->name('api.tools.pending');
        Route::post('/tools/{tool}/approve', [ToolController::class, 'approve'])->name('api.tools.approve');
        Route::post('/tools/{tool}/reject', [ToolController::class, 'reject'])->name('api.tools.reject');
    });

    Route::get('/tools/{tool:slug}', [ToolController::class, 'show'])->name('api.tools.show');
    Route::put('/tools/{tool}', [ToolController::class, 'update'])->name('api.tools.update');
    Route::delete('/tools/{tool}', [ToolController::class, 'destroy'])->name('api.tools.destroy');

    Route::put('/tools/{tool}/favorite', [FavoriteToolController::class, 'store'])->name('api.tools.favorite.store');
    Route::delete('/tools/{tool}/favorite', [FavoriteToolController::class, 'destroy'])->name('api.tools.favorite.destroy');

    Route::post('/tools/{tool}/images', [ToolImageController::class, 'store'])->name('api.tools.images.store');
    Route::delete('/tools/{tool}/images/{image}', [ToolImageController::class, 'destroy'])
        ->scopeBindings()
        ->name('api.tools.images.destroy');

    Route::get('/categories', [CategoryController::class, 'index'])->name('api.categories.index');
    Route::post('/categories', [CategoryController::class, 'store'])->name('api.categories.store');

    Route::middleware('role:admin')->group(function () {
        Route::get('/categories/pending', [CategoryController::class, 'pending'])->name('api.categories.pending');
        Route::post('/categories/{category}/approve', [CategoryController::class, 'approve'])->name('api.categories.approve');
        Route::post('/categories/{category}/reject', [CategoryController::class, 'reject'])->name('api.categories.reject');
        Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('api.categories.update');
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('api.categories.destroy');
    });

    Route::get('/tags', [TagController::class, 'index'])->name('api.tags.index');
    Route::post('/tags', [TagController::class, 'store'])->name('api.tags.store');

    Route::middleware('role:admin')->group(function () {
        Route::get('/tags/all', [TagController::class, 'all'])->name('api.tags.all');
        Route::get('/tags/pending', [TagController::class, 'pending'])->name('api.tags.pending');
        Route::post('/tags/{tag}/approve', [TagController::class, 'approve'])->name('api.tags.approve');
        Route::post('/tags/{tag}/reject', [TagController::class, 'reject'])->name('api.tags.reject');
        Route::put('/tags/{tag}', [TagController::class, 'update'])->name('api.tags.update');
        Route::delete('/tags/{tag}', [TagController::class, 'destroy'])->name('api.tags.destroy');
    });
});
