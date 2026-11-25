<?php

namespace App\Http\Requests\User;

use App\Enums\User\SystemRole;
use App\Models\User\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', User::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * `company_role` is a slug.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'system_role' => ['nullable', Rule::enum(SystemRole::class)],
            'company_role' => ['nullable', 'string', 'max:255', Rule::exists('company_roles', 'slug')],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
