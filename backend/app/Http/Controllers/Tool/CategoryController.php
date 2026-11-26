<?php

namespace App\Http\Controllers\Tool;

use App\Actions\Tool\SaveSuggestion;
use App\Enums\Activity\ActivityEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tool\StoreCategoryRequest;
use App\Http\Requests\Tool\UpdateCategoryRequest;
use App\Http\Resources\Tool\CategoryResource;
use App\Models\Tool\Category;
use App\Services\ActivityLogger;
use App\Services\CatalogCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CategoryController extends Controller
{
    /**
     * List the approved categories (the ones users can pick), each with the
     * number of its tools (deleted and pending tools are not counted).
     * The same for every user, so it is cached (see CatalogCache).
     */
    public function index(CatalogCache $cache): JsonResponse
    {
        return response()->json($cache->remember('categories', fn () => CategoryResource::collection(
            Category::approved()->withCount(['tools' => fn ($query) => $query->approved()])->orderBy('name')->get(),
        )->response()->getData(true)));
    }

    /**
     * Rename a category or change its description. The slug stays.
     */
    public function update(UpdateCategoryRequest $request, Category $category, ActivityLogger $activity): CategoryResource
    {
        DB::transaction(function () use ($request, $category, $activity) {
            $before = $category->only(['name', 'description']);
            $category->update($request->validated());

            $changes = $activity->diff($before, $category->only(['name', 'description']));

            if ($changes !== []) {
                $activity->log(ActivityEvent::CategoryUpdated, $category, $changes);
            }
        });

        return new CategoryResource($category->loadCount(['tools' => fn ($query) => $query->approved()]));
    }

    /**
     * Delete a category, approved or not. Tools keep their other categories.
     */
    public function destroy(Category $category, ActivityLogger $activity): Response
    {
        Gate::authorize('delete', $category);

        DB::transaction(function () use ($category, $activity) {
            $activity->log(ActivityEvent::CategoryDeleted, $category, $activity->deleted($category->only(['name', 'slug', 'description'])));

            $category->delete();
        });

        return response()->noContent();
    }

    /**
     * Suggest a category (the "Suggest a category" button in the tool form).
     * An admin's category is approved right away.
     */
    public function store(StoreCategoryRequest $request, SaveSuggestion $saveSuggestion): JsonResponse
    {
        $category = $saveSuggestion->handle($request->user(), new Category($request->validated()));

        return (new CategoryResource($category))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * List the suggestions waiting for an admin, by name.
     */
    public function pending(): AnonymousResourceCollection
    {
        Gate::authorize('viewPending', Category::class);

        $categories = Category::pending()
            ->with('suggestedBy:id,name')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return CategoryResource::collection($categories);
    }

    /**
     * Approve a suggestion, so users can pick it.
     */
    public function approve(Category $category, ActivityLogger $activity): CategoryResource|JsonResponse
    {
        Gate::authorize('approve', $category);

        if ($category->isApproved()) {
            return $this->alreadyApproved();
        }

        DB::transaction(function () use ($category, $activity) {
            $category->approved_at = now();
            $category->save();

            $activity->log(ActivityEvent::CategoryApproved, $category, properties: [
                'suggested_by' => $category->suggestedBy()->value('name'),
            ]);
        });

        return new CategoryResource($category);
    }

    /**
     * Reject a suggestion: it is deleted, so the name can be suggested again.
     */
    public function reject(Category $category, ActivityLogger $activity): JsonResponse|Response
    {
        Gate::authorize('reject', $category);

        if ($category->isApproved()) {
            return $this->alreadyApproved();
        }

        DB::transaction(function () use ($category, $activity) {
            $activity->log(ActivityEvent::CategoryRejected, $category, properties: [
                'suggested_by' => $category->suggestedBy()->value('name'),
            ]);

            $category->delete();
        });

        return response()->noContent();
    }

    /**
     * Approved categories are not suggestions any more and cannot be decided on again.
     */
    private function alreadyApproved(): JsonResponse
    {
        return response()->json([
            'message' => 'The category is already approved.',
        ], Response::HTTP_CONFLICT);
    }
}
