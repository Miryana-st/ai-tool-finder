<?php

namespace App\Actions\User;

use App\Models\User\User;
use Illuminate\Support\Facades\DB;

class DeleteUser
{
    /**
     * Delete the user and end their sessions.
     *
     * What the user added stays: tools (deleted ones included), comments and
     * category and tag suggestions keep their record without an author
     * (`user_id` / `suggested_by_id` is cleared by the database).
     */
    public function handle(User $user): void
    {
        DB::transaction(function () use ($user) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->delete();
        });
    }
}
