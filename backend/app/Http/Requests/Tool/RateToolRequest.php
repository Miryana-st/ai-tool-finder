<?php

namespace App\Http\Requests\Tool;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class RateToolRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request (see ToolPolicy::rate()).
     */
    public function authorize(): Response
    {
        return Gate::inspect('rate', $this->route('tool'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,5'],
        ];
    }
}
