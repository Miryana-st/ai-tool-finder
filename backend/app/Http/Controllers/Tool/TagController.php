<?php

namespace App\Http\Controllers\Tool;

use App\Actions\Tool\SaveSuggestion;
use App\Enums\Activity\ActivityEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tool\StoreTagRequest;
use App\Http\Requests\Tool\UpdateTagRequest;
use App\Http\Resources\Tool\TagResource;
use App\Models\Tool\Tag;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
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
     * List every approved tag by name, each with the number of its tools
     * (deleted and pending tools are not counted). For the admin tag management page.
     */
    public function all(): AnonymousResourceCollection
    {
        Gate::authorize('viewAll', Tag::class);

        return TagResource::collection(Tag::approved()->withCount(['tools' => fn ($query) => $query->approved()])->orderBy('name')->get());
    }

    /**
     * Rename a tag. The slug stays.
     */
    public function update(UpdateTagRequest $request, Tag $tag, ActivityLogger $activity): TagResource
    {
        DB::transaction(function () use ($request, $tag, $activity) {
            $before = $tag->only(['name']);
            $tag->update($request->validated());

            $changes = $activity->diff($before, $tag->only(['name']));

            if ($changes !== []) {
                $activity->log(ActivityEvent::TagUpdated, $tag, $changes);
            }
        });

        return new TagResource($tag->loadCount(['tools' => fn ($query) => $query->approved()]));
    }

    /**
     * Delete a tag, approved or not. Tools keep their other tags.
     */
    public function destroy(Tag $tag, ActivityLogger $activity): Response
    {
        Gate::authorize('delete', $tag);

        DB::transaction(function () use ($tag, $activity) {
            $activity->log(ActivityEvent::TagDeleted, $tag, $activity->deleted($tag->only(['name', 'slug'])));

            $tag->delete();
        });

        return response()->noContent();
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
     * List the suggestions waiting for an admin, by name.
     */
    public function pending(): AnonymousResourceCollection
    {
        Gate::authorize('viewPending', Tag::class);

        $tags = Tag::pending()
            ->with('suggestedBy:id,name')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return TagResource::collection($tags);
    }

    /**
     * Approve a suggestion, so users can pick it.
     */
    public function approve(Tag $tag, ActivityLogger $activity): TagResource|JsonResponse
    {
        Gate::authorize('approve', $tag);

        if ($tag->isApproved()) {
            return $this->alreadyApproved();
        }

        DB::transaction(function () use ($tag, $activity) {
            $tag->approved_at = now();
            $tag->save();

            $activity->log(ActivityEvent::TagApproved, $tag, properties: [
                'suggested_by' => $tag->suggestedBy()->value('name'),
            ]);
        });

        return new TagResource($tag);
    }

    /**
     * Reject a suggestion: it is deleted, so the name can be suggested again.
     */
    public function reject(Tag $tag, ActivityLogger $activity): JsonResponse|Response
    {
        Gate::authorize('reject', $tag);

        if ($tag->isApproved()) {
            return $this->alreadyApproved();
        }

        DB::transaction(function () use ($tag, $activity) {
            $activity->log(ActivityEvent::TagRejected, $tag, properties: [
                'suggested_by' => $tag->suggestedBy()->value('name'),
            ]);

            $tag->delete();
        });

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
