<?php

namespace App\Http\Requests\Tool;

use App\Models\Tool\Category;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
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
     * The name and the slug made from it are unique among pending suggestions
     * too, so the same category cannot be suggested twice. The slug check also
     * catches names that differ only in punctuation ("Код и IDE", "Код-и-IDE").
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique(Category::class),
                function (string $attribute, mixed $value, Closure $fail) {
                    if (! is_string($value)) {
                        return;
                    }

                    $slug = Str::slug($value);

                    if ($slug === '') {
                        $fail('The category name must contain at least one letter or number.');
                    } elseif (Category::query()->where('slug', $slug)->exists()) {
                        $fail('A category with a similar name already exists.');
                    }
                },
            ],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
