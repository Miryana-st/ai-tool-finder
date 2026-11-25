<?php

namespace App\Http\Requests\Tool;

use App\Models\Tool\Tag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTagRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Tag::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The name and the slug are unique among pending suggestions too, so the
     * same tag cannot be suggested twice. The slug is always given.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:30', Rule::unique(Tag::class)],
            'slug' => ['required', 'string', 'max:30', 'regex:'.SlugFormat::PATTERN, Rule::unique(Tag::class)],
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
