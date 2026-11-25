<?php

namespace App\Http\Controllers\Tool;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tool\TagResource;
use App\Models\Tool\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TagController extends Controller
{
    /**
     * Maximum number of tags returned for autocomplete.
     */
    private const int LIMIT = 20;

    /**
     * List tags for autocomplete, optionally those whose name contains `search`.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:30'],
        ]);

        $tags = Tag::query()
            ->when($validated['search'] ?? null, fn ($query, string $search) => $query->whereLike('name', '%'.$search.'%'))
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get();

        return TagResource::collection($tags);
    }
}
