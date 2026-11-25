<?php

namespace App\Http\Controllers;

use App\Actions\Users\UpdateUserSystemRole;
use App\Enums\SystemRole;
use App\Http\Requests\UpdateUserSystemRoleRequest;
use App\Http\Resources\UserResource;
use App\Models\User;

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
