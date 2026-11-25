<?php

namespace App\Actions\User;

use App\Models\User\User;
use Illuminate\Support\Facades\DB;

class DeleteUser
{
    /**
     * Delete the user and end their sessions.
     *
     * Category and tag suggestions keep their record (`suggested_by_id` is
     * cleared by the database). Users who added tools, deleted ones included,
     * cannot be deleted: the tools keep their creator (restrictOnDelete).
     *
     * @return bool False when the user has tools and was not deleted.
     */
    public function handle(User $user): bool
    {
        return DB::transaction(function () use ($user) {
            if ($user->tools()->withTrashed()->exists()) {
                return false;
            }

            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->delete();

            return true;
        });
    }
}
