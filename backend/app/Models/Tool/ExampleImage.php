<?php

namespace App\Models\Tool;

use App\Models\User\User;
use Database\Factories\Tool\ExampleImageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A screenshot uploaded for the Markdown `examples` of a tool. It belongs to
 * no tool: the examples text refers to it by its URL. `path` is relative to
 * the public disk.
 */
#[Fillable(['path', 'original_name'])]
class ExampleImage extends Model
{
    /** @use HasFactory<ExampleImageFactory> */
    use HasFactory;

    /**
     * Get the user who uploaded the image.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The URL the examples text refers to. It is root-relative ("/storage/…"):
     * the text is stored, so it must keep working if the site's address changes.
     */
    public function url(): string
    {
        return (string) parse_url(Storage::disk('public')->url($this->path), PHP_URL_PATH);
    }
}
