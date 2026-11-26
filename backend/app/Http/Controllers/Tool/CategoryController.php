<?php

namespace App\Http\Controllers\Tool;

use App\Actions\Tool\SaveSuggestion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tool\StoreCategoryRequest;
use App\Http\Requests\Tool\UpdateCategoryRequest;
use App\Http\Resources\Tool\CategoryResource;
use App\Models\Tool\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class CategoryController extends Controller
{
    /**
     * List the approved categories (the ones users can pick), each with the
     * number of its tools (deleted tools are not counted).
     */
    public function index(): AnonymousResourceCollection
    {
        return CategoryResource::collection(
            Category::approved()->withCount('tools')->orderBy('name')->get(),
        );
    }

    /**
     * Rename a category or change its description. The slug stays.
     */
    public function update(UpdateCategoryRequest $request, Category $category): CategoryResource
    {
        $category->update($request->validated());

        return new CategoryResource($category->loadCount('tools'));
    }

    /**
     * Delete a category, approved or not. Tools keep their other categories.
     */
    public function destroy(Category $category): Response
    {
        Gate::authorize('delete', $category);

        $category->delete();

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
     * List the suggestions waiting for an admin, oldest first.
     */
    public function pending(): AnonymousResourceCollection
    {
        Gate::authorize('viewPending', Category::class);

        $categories = Category::pending()
            ->with('suggestedBy:id,name')
            ->oldest()
            ->oldest('id')
            ->get();

        return CategoryResource::collection($categories);
    }

    /**
     * Approve a suggestion, so users can pick it.
     */
    public function approve(Category $category): CategoryResource|JsonResponse
    {
        Gate::authorize('approve', $category);

        if ($category->isApproved()) {
            return $this->alreadyApproved();
        }

        $category->approved_at = now();
        $category->save();

        return new CategoryResource($category);
    }

    /**
     * Reject a suggestion: it is deleted, so the name can be suggested again.
     */
    public function reject(Category $category): JsonResponse|Response
    {
        Gate::authorize('reject', $category);

        if ($category->isApproved()) {
            return $this->alreadyApproved();
        }

        $category->delete();

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
