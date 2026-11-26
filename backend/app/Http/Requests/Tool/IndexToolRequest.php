<?php

namespace App\Http\Requests\Tool;

use Illuminate\Foundation\Http\FormRequest;

class IndexToolRequest extends FormRequest
{
    /**
     * Maximum number of tags to filter by at once.
     */
    public const int MAX_TAGS = 10;

    /**
     * Get the validation rules that apply to the request.
     *
     * `company_role`, `category` and each of `tags` are slugs; a tool must have
     * all of the given tags. `favorites` keeps only the signed-in user's
     * favorite tools and `mine` only the tools the signed-in user added.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'company_role' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'array', 'max:'.self::MAX_TAGS],
            'tags.*' => ['required', 'string', 'max:255'],
            'favorites' => ['nullable', 'boolean'],
            'mine' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
