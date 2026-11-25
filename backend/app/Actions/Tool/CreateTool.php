<?php

namespace App\Actions\Tool;

use App\Models\Tool\Tool;
use App\Models\User\User;
use Illuminate\Support\Facades\DB;

class CreateTool
{
    use SyncsToolRelations;

    /**
     * Add a tool created by the user, with its categories, company roles and tags.
     * Nothing is saved if any step fails.
     *
     * @param  array<string, mixed>  $data  validated StoreToolRequest input
     */
    public function handle(User $user, array $data): Tool
    {
        return DB::transaction(function () use ($user, $data) {
            $tool = $user->tools()->create($this->toolAttributes($data));

            $this->syncRelations($tool, $data);

            return $tool;
        });
    }
}
