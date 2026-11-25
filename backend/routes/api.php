<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Tool\CategoryController;
use App\Http\Controllers\Tool\TagController;
use App\Http\Controllers\Tool\ToolController;
use App\Http\Controllers\Tool\ToolImageController;
use App\Http\Controllers\User\CompanyRoleController;
use App\Http\Controllers\User\UserSystemRoleController;
use App\Http\Resources\User\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/company-roles', [CompanyRoleController::class, 'index'])
    ->name('api.company-roles.index');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $request) => new UserResource($request->user()))
        ->name('api.user');

    Route::get('/dashboard', DashboardController::class)
        ->name('api.dashboard');

    Route::apiResource('company-roles', CompanyRoleController::class)
        ->only(['store', 'update', 'destroy'])
        ->names('api.company-roles');

    Route::patch('/users/{user}/system-role', UserSystemRoleController::class)
        ->name('api.users.system-role.update');

    Route::get('/tools', [ToolController::class, 'index'])->name('api.tools.index');
    Route::post('/tools', [ToolController::class, 'store'])->name('api.tools.store');
    Route::get('/tools/{tool:slug}', [ToolController::class, 'show'])->name('api.tools.show');
    Route::put('/tools/{tool}', [ToolController::class, 'update'])->name('api.tools.update');
    Route::delete('/tools/{tool}', [ToolController::class, 'destroy'])->name('api.tools.destroy');

    Route::post('/tools/{tool}/images', [ToolImageController::class, 'store'])->name('api.tools.images.store');
    Route::delete('/tools/{tool}/images/{image}', [ToolImageController::class, 'destroy'])
        ->scopeBindings()
        ->name('api.tools.images.destroy');

    Route::get('/categories', [CategoryController::class, 'index'])->name('api.categories.index');
    Route::post('/categories', [CategoryController::class, 'store'])->name('api.categories.store');
    Route::get('/categories/pending', [CategoryController::class, 'pending'])->name('api.categories.pending');
    Route::post('/categories/{category}/approve', [CategoryController::class, 'approve'])->name('api.categories.approve');
    Route::delete('/categories/{category}', [CategoryController::class, 'reject'])->name('api.categories.reject');

    Route::get('/tags', [TagController::class, 'index'])->name('api.tags.index');
});
