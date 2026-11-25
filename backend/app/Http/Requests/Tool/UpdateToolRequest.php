<?php

namespace App\Http\Requests\Tool;

use Illuminate\Validation\Rules\Unique;

/**
 * A full replacement (PUT): the same rules as creating a tool, but the
 * tool's own name does not count as taken.
 */
class UpdateToolRequest extends StoreToolRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('tool'));
    }

    /**
     * Get the unique rule for the tool name, ignoring the tool being updated.
     */
    protected function uniqueNameRule(): Unique
    {
        return parent::uniqueNameRule()->ignore($this->route('tool'));
    }
}
