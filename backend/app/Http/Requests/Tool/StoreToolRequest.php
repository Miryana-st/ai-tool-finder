<?php

namespace App\Http\Requests\Tool;

use App\Models\Tool\Tool;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
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
            'documentation_url' => ['nullable', 'string', 'max:255', 'url:http,https'],
            'description' => ['required', 'string'],
            'how_to_use' => ['required', 'string'],
            'examples' => ['nullable', 'string'],
            'company_roles' => ['required', 'array', 'min:1'],
            'company_roles.*' => ['integer', 'distinct', 'exists:company_roles,id'],
            'categories' => ['required', 'array', 'min:1'],
            'categories.*' => ['integer', 'distinct', 'exists:categories,id'],
            'tags' => ['nullable', 'array'],
            'tags.*' => [
                'string',
                'max:30',
                function (string $attribute, mixed $value, Closure $fail) {
                    if (is_string($value) && Str::slug($value) === '') {
                        $fail('The tag must contain at least one letter or number.');
                    }
                },
            ],
        ];
    }

    /**
     * Get the unique rule for the tool name.
     */
    protected function uniqueNameRule(): Unique
    {
        return Rule::unique(Tool::class);
    }

    /**
     * Normalize the tags: trim them and drop empty and case-insensitive duplicates.
     * Empty strings arrive as null (ConvertEmptyStringsToNull middleware).
     */
    protected function prepareForValidation(): void
    {
        $tags = $this->input('tags');

        if (! is_array($tags)) {
            return;
        }

        $normalized = [];

        foreach ($tags as $tag) {
            if ($tag === null) {
                continue;
            }

            if (! is_string($tag)) {
                $normalized[] = $tag;

                continue;
            }

            $tag = trim($tag);

            if ($tag !== '') {
                $normalized[mb_strtolower($tag)] ??= $tag;
            }
        }

        $this->merge(['tags' => array_values($normalized)]);
    }
}
