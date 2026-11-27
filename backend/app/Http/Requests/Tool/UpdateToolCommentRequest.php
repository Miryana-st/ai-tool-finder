<?php

namespace App\Http\Requests\Tool;

use Illuminate\Foundation\Http\FormRequest;

class UpdateToolCommentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request: only the author.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('comment'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:'.StoreToolCommentRequest::MAX_LENGTH],
        ];
    }
}
