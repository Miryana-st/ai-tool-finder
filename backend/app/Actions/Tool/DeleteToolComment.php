<?php

namespace App\Actions\Tool;

use App\Enums\Activity\ActivityEvent;
use App\Models\Tool\ToolComment;
use App\Models\User\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DeleteToolComment
{
    public function __construct(private readonly ActivityLogger $activity) {}

    /**
     * Delete the comment. When somebody other than the author (an admin)
     * deletes it, the activity history keeps who wrote it and what it said.
     */
    public function handle(ToolComment $comment, User $deletedBy): void
    {
        DB::transaction(function () use ($comment, $deletedBy) {
            $comment->delete();

            if ($comment->user_id !== $deletedBy->id) {
                $this->activity->log(ActivityEvent::ToolCommentDeleted, $comment->tool, properties: [
                    'author' => $comment->user?->name,
                    'comment' => Str::limit($comment->body, 200),
                ], user: $deletedBy);
            }
        });
    }
}
