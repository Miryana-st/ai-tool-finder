<?php

namespace App\Http\Requests\Tool;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreToolCommentRequest extends FormRequest
{
    /**
     * Longest comment, in characters.
     */
    public const int MAX_LENGTH = 2000;

    /**
     * Determine if the user is authorized to make this request (see ToolPolicy::comment()).
     */
    public function authorize(): Response
    {
        return Gate::inspect('comment', $this->route('tool'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:'.self::MAX_LENGTH],
        ];
    }
}
