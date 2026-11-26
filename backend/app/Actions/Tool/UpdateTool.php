<?php

namespace App\Actions\Tool;

use App\Enums\Activity\ActivityEvent;
use App\Models\Tool\Tool;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;

class UpdateTool
{
    use SyncsToolRelations;

    public function __construct(private readonly ActivityLogger $activity) {}

    /**
     * Replace the tool's data, categories, company roles and tags.
     * Nothing is changed if any step fails. The slug stays the same.
     * The activity history gets an entry only if something really changed.
     *
     * @param  array<string, mixed>  $data  validated UpdateToolRequest input
     */
    public function handle(Tool $tool, array $data): Tool
    {
        return DB::transaction(function () use ($tool, $data) {
            $before = $tool->activityValues();

            $tool->update($this->toolAttributes($data));

            $this->syncRelations($tool, $data);

            $changes = $this->activity->diff($before, $tool->activityValues());

            if ($changes !== []) {
                $this->activity->log(ActivityEvent::ToolUpdated, $tool, $changes);
            }

            return $tool;
        });
    }
}
