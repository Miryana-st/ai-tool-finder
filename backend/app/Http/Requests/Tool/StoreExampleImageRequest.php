<?php

namespace App\Http\Requests\Tool;

use Illuminate\Foundation\Http\FormRequest;

class StoreExampleImageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request: any signed-in
     * user, since anybody can add a tool and write its examples.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request. The same limits as
     * the tool screenshots (StoreToolImageRequest).
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'image' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
        ];
    }
}
