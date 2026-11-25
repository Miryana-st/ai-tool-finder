<?php

namespace App\Http\Requests;

use App\Models\CompanyRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('company_role'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The slug cannot change: dashboard menus in config/menus.php are keyed by it.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'slug' => ['prohibited'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(CompanyRole::class)->ignore($this->route('company_role')),
            ],
        ];
    }
}
