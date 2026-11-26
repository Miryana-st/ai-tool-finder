<?php

namespace App\Actions\User;

use App\Enums\User\SystemRole;
use App\Models\User\CompanyRole;
use App\Models\User\User;
use Illuminate\Support\Facades\Hash;

class CreateUser
{
    /**
     * Create a user account on behalf of an admin, with the password the admin chose.
     *
     * @param  array{name: string, email: string, password: string, company_role: string, system_role: string}  $data
     */
    public function handle(array $data): User
    {
        $user = new User([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'company_role_id' => CompanyRole::where('slug', $data['company_role'])->value('id'),
        ]);

        // system_role is not mass assignable, so it is set on purpose here.
        $user->forceFill(['system_role' => SystemRole::from($data['system_role'])])->save();

        return $user;
    }
}
