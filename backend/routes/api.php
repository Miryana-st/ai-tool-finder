<?php

use App\Http\Controllers\CompanyRoleController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserSystemRoleController;
use App\Http\Resources\UserResource;
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
});
