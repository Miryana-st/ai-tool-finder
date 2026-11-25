<?php

namespace App\Policies\User;

use App\Models\User\CompanyRole;
use App\Models\User\User;

class CompanyRolePolicy
{
    /**
     * Determine whether the user can add company roles.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can rename the company role.
     */
    public function update(User $user, CompanyRole $companyRole): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can delete the company role.
     */
    public function delete(User $user, CompanyRole $companyRole): bool
    {
        return $user->isAdmin();
    }
}
