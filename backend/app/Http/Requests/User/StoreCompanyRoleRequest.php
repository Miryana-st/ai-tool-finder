<?php

namespace App\Http\Requests\User;

use App\Models\User\CompanyRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', CompanyRole::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'slug' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                Rule::unique(CompanyRole::class),
            ],
            'name' => ['required', 'string', 'max:255', Rule::unique(CompanyRole::class)],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'The slug may only contain lowercase letters, numbers and single dashes.',
        ];
    }
}
