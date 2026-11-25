<?php

namespace App\Actions\Tool;

use App\Models\Tool\Tool;
use Illuminate\Support\Facades\DB;

class UpdateTool
{
    use SyncsToolRelations;

    /**
     * Replace the tool's data, categories, company roles and tags.
     * Nothing is changed if any step fails. The slug stays the same.
     *
     * @param  array<string, mixed>  $data  validated UpdateToolRequest input
     */
    public function handle(Tool $tool, array $data): Tool
    {
        return DB::transaction(function () use ($tool, $data) {
            $tool->update($this->toolAttributes($data));

            $this->syncRelations($tool, $data);

            return $tool;
        });
    }
}
