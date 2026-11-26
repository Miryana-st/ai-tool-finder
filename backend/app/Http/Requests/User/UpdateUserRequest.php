<?php

namespace App\Http\Requests\User;

use App\Models\User\CompanyRole;
use App\Models\User\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('user'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The password is optional: left empty, it stays the same. The system role
     * has its own endpoint (PATCH /api/users/{user}/system-role).
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->route('user')),
            ],
            'company_role' => ['required', 'string', Rule::exists(CompanyRole::class, 'slug')],
            'password' => ['nullable', 'string', Password::default(), 'confirmed'],
        ];
    }
}
