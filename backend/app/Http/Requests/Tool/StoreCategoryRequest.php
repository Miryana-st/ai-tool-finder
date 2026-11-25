<?php

namespace App\Http\Requests\Tool;

use App\Models\Tool\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Category::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The name and the slug are unique among pending suggestions too, so the
     * same category cannot be suggested twice. The slug is always given: it
     * is used in links and filters, so it is never derived from the name here.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50', Rule::unique(Category::class)],
            'slug' => ['required', 'string', 'max:50', 'regex:'.SlugFormat::PATTERN, Rule::unique(Category::class)],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['slug.regex' => SlugFormat::MESSAGE];
    }
}
