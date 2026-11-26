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
     * Nothing is saved if any step fails. An admin's tool is approved right away;
     * anybody else's waits for an admin before everybody can see it.
     *
     * @param  array<string, mixed>  $data  validated StoreToolRequest input
     */
    public function handle(User $user, array $data): Tool
    {
        return DB::transaction(function () use ($user, $data) {
            $tool = $user->tools()->make($this->toolAttributes($data));
            $tool->approved_at = $user->isAdmin() ? now() : null;
            $tool->save();

            $this->syncRelations($tool, $data);

            return $tool;
        });
    }
}
