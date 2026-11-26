<?php

namespace App\Http\Requests\Tool;

use App\Models\Tool\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('category'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Only the name and the description change. The slug stays, so links and
     * filters that use it keep working.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50', Rule::unique(Category::class)->ignore($this->route('category'))],
            'description' => ['nullable', 'string', 'max:500'],
            'slug' => ['prohibited'],
        ];
    }
}
