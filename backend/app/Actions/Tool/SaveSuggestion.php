<?php

namespace App\Actions\Tool;

use App\Enums\Activity\ActivityEvent;
use App\Models\Tool\Category;
use App\Models\Tool\Tag;
use App\Models\User\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;

class SaveSuggestion
{
    public function __construct(private readonly ActivityLogger $activity) {}

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
        return DB::transaction(function () use ($user, $record) {
            $record->suggestedBy()->associate($user);
            $record->approved_at = $user->isAdmin() ? now() : null;
            $record->save();

            $this->activity->log(
                $record instanceof Category ? ActivityEvent::CategoryCreated : ActivityEvent::TagCreated,
                $record,
                $this->activity->created($record->only(['name', 'slug', 'description'])),
                ['approved' => $record->isApproved()],
                $user,
            );

            return $record;
        });
    }
}
