<?php

namespace App\Http\Controllers\Tool;

use App\Actions\Tool\SaveSuggestion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tool\StoreTagRequest;
use App\Http\Resources\Tool\TagResource;
use App\Models\Tool\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Tags work like categories: users suggest them, an admin approves them,
 * and only approved tags can be picked for a tool.
 */
class TagController extends Controller
{
    /**
     * Maximum number of tags returned for autocomplete.
     */
    private const int LIMIT = 20;

    /**
     * List approved tags for autocomplete, optionally those whose name contains `search`.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:30'],
        ]);

        $tags = Tag::approved()
            ->when($validated['search'] ?? null, fn ($query, string $search) => $query->whereLike('name', '%'.$search.'%'))
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get();

        return TagResource::collection($tags);
    }

    /**
     * Suggest a tag (the "Suggest a tag" button in the tool form).
     * An admin's tag is approved right away.
     */
    public function store(StoreTagRequest $request, SaveSuggestion $saveSuggestion): JsonResponse
    {
        $tag = $saveSuggestion->handle($request->user(), new Tag($request->validated()));

        return (new TagResource($tag))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * List the suggestions waiting for an admin, oldest first.
     */
    public function pending(): AnonymousResourceCollection
    {
        Gate::authorize('viewPending', Tag::class);

        $tags = Tag::pending()
            ->with('suggestedBy:id,name')
            ->oldest()
            ->oldest('id')
            ->get();

        return TagResource::collection($tags);
    }

    /**
     * Approve a suggestion, so users can pick it.
     */
    public function approve(Tag $tag): TagResource|JsonResponse
    {
        Gate::authorize('approve', $tag);

        if ($tag->isApproved()) {
            return $this->alreadyApproved();
        }

        $tag->approved_at = now();
        $tag->save();

        return new TagResource($tag);
    }

    /**
     * Reject a suggestion: it is deleted, so the name can be suggested again.
     */
    public function reject(Tag $tag): JsonResponse|Response
    {
        Gate::authorize('reject', $tag);

        if ($tag->isApproved()) {
            return $this->alreadyApproved();
        }

        $tag->delete();

        return response()->noContent();
    }

    /**
     * Approved tags are not suggestions any more and cannot be decided on again.
     */
    private function alreadyApproved(): JsonResponse
    {
        return response()->json([
            'message' => 'The tag is already approved.',
        ], Response::HTTP_CONFLICT);
    }
}
