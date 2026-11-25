<?php

namespace App\Policies;

use App\Models\CompanyRole;
use App\Models\User;

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
