<?php

namespace App\Actions\Fortify;

use App\Models\User\CompanyRole;
use App\Models\User\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * Users must pick their company role, but not one reserved for others to assign.
     * New users always get the `user` system role; only an admin can make them admin.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
            'company_role' => [
                'required',
                'string',
                Rule::exists(CompanyRole::class, 'slug')->whereNotIn('slug', CompanyRole::NOT_SELF_ASSIGNABLE),
            ],
        ])->validate();

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
            'company_role_id' => CompanyRole::where('slug', $input['company_role'])->value('id'),
        ]);
    }
}
