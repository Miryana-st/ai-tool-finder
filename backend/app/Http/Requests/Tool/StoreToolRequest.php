<?php

namespace App\Http\Requests\Tool;

use App\Models\Tool\Tool;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class StoreToolRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Tool::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The name is unique across soft-deleted tools too, like the database index.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', $this->uniqueNameRule()],
            'url' => ['required', 'string', 'max:255', 'url:http,https'],
            'documentation_url' => ['required', 'string', 'max:255', 'url:http,https'],
            'description' => ['required', 'string'],
            'how_to_use' => ['required', 'string'],
            'examples' => ['nullable', 'string'],
            'company_roles' => ['required', 'array', 'min:1'],
            'company_roles.*' => ['integer', 'distinct', 'exists:company_roles,id'],
            'categories' => ['required', 'array', 'min:1'],
            // Pending suggestions cannot be picked until an admin approves them.
            'categories.*' => ['integer', 'distinct', Rule::exists('categories', 'id')->whereNotNull('approved_at')],
            'tags' => ['nullable', 'array'],
            // Tags are picked from the approved ones; new tags are suggested separately.
            'tags.*' => ['integer', 'distinct', Rule::exists('tags', 'id')->whereNotNull('approved_at')],
        ];
    }

    /**
     * Get the unique rule for the tool name.
     */
    protected function uniqueNameRule(): Unique
    {
        return Rule::unique(Tool::class);
    }
}
