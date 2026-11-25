<?php

namespace App\Http\Controllers\User;

use App\Actions\User\UpdateUserSystemRole;
use App\Enums\User\SystemRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\UpdateUserSystemRoleRequest;
use App\Http\Resources\User\UserResource;
use App\Models\User\User;

class UserSystemRoleController extends Controller
{
    /**
     * Change the system role of the given user (admins only).
     */
    public function __invoke(UpdateUserSystemRoleRequest $request, User $user, UpdateUserSystemRole $updateSystemRole): UserResource
    {
        $user = $updateSystemRole->handle($user, SystemRole::from($request->validated('system_role')));

        return new UserResource($user->loadMissing('companyRole'));
    }
}
