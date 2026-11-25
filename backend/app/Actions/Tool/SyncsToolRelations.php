<?php

namespace App\Actions\Tool;

use App\Models\Tool\Tool;

/**
 * Shared steps of CreateTool and UpdateTool. Both run inside a transaction.
 */
trait SyncsToolRelations
{
    /**
     * Get the tool columns from validated input. Optional fields that are
     * missing are cleared, because a tool is always saved as a whole.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function toolAttributes(array $data): array
    {
        return [
            'name' => $data['name'],
            'url' => $data['url'],
            'documentation_url' => $data['documentation_url'],
            'description' => $data['description'],
            'how_to_use' => $data['how_to_use'],
            'examples' => $data['examples'] ?? null,
        ];
    }

    /**
     * Replace the tool's categories, company roles and tags with the given ids.
     * All of them exist already: new tags are suggested separately, not here.
     *
     * @param  array<string, mixed>  $data
     */
    private function syncRelations(Tool $tool, array $data): void
    {
        $tool->categories()->sync($data['categories']);
        $tool->companyRoles()->sync($data['company_roles']);
        $tool->tags()->sync($data['tags'] ?? []);
    }
}
