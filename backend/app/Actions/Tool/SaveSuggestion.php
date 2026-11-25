<?php

namespace App\Actions\Tool;

use App\Models\Tool\Category;
use App\Models\Tool\Tag;
use App\Models\User\User;

class SaveSuggestion
{
    /**
     * Save a category or tag suggested by the user. An admin's suggestion is
     * approved right away; anybody else's waits for an admin.
     *
     * @template T of Category|Tag
     *
     * @param  T  $record  a new, unsaved record filled with the validated input
     * @return T
     */
    public function handle(User $user, Category|Tag $record): Category|Tag
    {
        $record->suggestedBy()->associate($user);
        $record->approved_at = $user->isAdmin() ? now() : null;
        $record->save();

        return $record;
    }
}
