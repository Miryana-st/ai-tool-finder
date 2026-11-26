<?php

namespace App\Http\Requests\Tool;

use App\Models\Tool\Tag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTagRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('tag'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Only the name changes. The slug stays, so filters that use it keep working.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:30', Rule::unique(Tag::class)->ignore($this->route('tag'))],
            'slug' => ['prohibited'],
        ];
    }
}
