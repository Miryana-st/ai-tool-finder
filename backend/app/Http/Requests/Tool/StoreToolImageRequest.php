<?php

namespace App\Http\Requests\Tool;

use App\Models\Tool\Tool;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * One screenshot per request. Who may edit the tool may add screenshots.
 */
class StoreToolImageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('tool'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'image' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                /** @var Tool $tool */
                $tool = $this->route('tool');

                if ($tool->images()->count() >= Tool::MAX_IMAGES) {
                    $validator->errors()->add(
                        'image',
                        'A tool can have at most '.Tool::MAX_IMAGES.' images.',
                    );
                }
            },
        ];
    }
}
