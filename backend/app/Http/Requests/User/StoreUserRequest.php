<?php

namespace App\Http\Requests\User;

use App\Enums\User\SystemRole;
use App\Models\User\CompanyRole;
use App\Models\User\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Unlike self-registration, an admin may give any company role (owner
     * included) and the admin system role. The user can change the password
     * later (PUT /api/user/password).
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
            'company_role' => ['required', 'string', Rule::exists(CompanyRole::class, 'slug')],
            'system_role' => ['required', Rule::enum(SystemRole::class)],
        ];
    }
}
