<?php

namespace App\Models\Tool;

use App\Models\User\User;
use Database\Factories\Tool\ToolCommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's comment on a tool. `user_id` (the author) is not fillable: it is
 * set through the relationship, never from request input. It is null once
 * the author's account is deleted.
 */
#[Fillable(['body'])]
class ToolComment extends Model
{
    /** @use HasFactory<ToolCommentFactory> */
    use HasFactory;

    /**
     * Get the tool the comment is about.
     *
     * @return BelongsTo<Tool, $this>
     */
    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class);
    }

    /**
     * Get the author of the comment.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Determine whether the comment was changed after it was written.
     */
    public function isEdited(): bool
    {
        return $this->edited_at !== null;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'edited_at' => 'datetime',
        ];
    }

    /**
     * Mark the comment as edited when its text changes (not on the first save).
     * `edited_at` is not fillable: only this sets it.
     */
    protected static function booted(): void
    {
        static::updating(function (ToolComment $comment) {
            if ($comment->isDirty('body')) {
                $comment->edited_at = now();
            }
        });
    }
}
