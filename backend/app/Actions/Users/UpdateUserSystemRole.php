<?php

namespace App\Actions\Users;

use App\Enums\SystemRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateUserSystemRole
{
    /**
     * Change the user's system role, making sure at least one admin always remains.
     *
     * @throws ValidationException
     */
    public function handle(User $user, SystemRole $systemRole): User
    {
        return DB::transaction(function () use ($user, $systemRole) {
            $isDemotingAnAdmin = $user->isAdmin() && $systemRole !== SystemRole::Admin;

            if ($isDemotingAnAdmin) {
                $adminCount = User::where('system_role', SystemRole::Admin)->lockForUpdate()->count();

                if ($adminCount <= 1) {
                    throw ValidationException::withMessages([
                        'system_role' => 'The last admin cannot lose the admin role.',
                    ]);
                }
            }

            $user->forceFill(['system_role' => $systemRole])->save();

            return $user;
        });
    }
}
