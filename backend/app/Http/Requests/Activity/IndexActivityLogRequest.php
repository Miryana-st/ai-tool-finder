<?php

namespace App\Http\Requests\Activity;

use App\Enums\Activity\ActivityEvent;
use App\Enums\Activity\ActivityGroup;
use App\Models\Activity\ActivityLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexActivityLogRequest extends FormRequest
{
    /**
     * The kinds of records an entry can be about.
     *
     * @var list<string>
     */
    public const array SUBJECT_TYPES = ['tool', 'category', 'tag'];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', ActivityLog::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * `created_from` and `created_before` are points in time with a time zone
     * (the frontend sends the start of the chosen days in the admin's zone).
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'user_id' => ['nullable', 'integer', 'min:1'],
            'group' => ['nullable', Rule::enum(ActivityGroup::class)],
            'event' => ['nullable', Rule::enum(ActivityEvent::class)],
            'subject_type' => ['nullable', Rule::in(self::SUBJECT_TYPES)],
            'subject_id' => ['nullable', 'integer', 'min:1', 'required_with:subject_type'],
            'created_from' => ['nullable', 'date'],
            'created_before' => ['nullable', 'date', 'after:created_from'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
