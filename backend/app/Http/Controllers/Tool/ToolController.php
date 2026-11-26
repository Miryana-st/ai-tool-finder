<?php

namespace App\Http\Controllers\Tool;

use App\Actions\Tool\CreateTool;
use App\Actions\Tool\UpdateTool;
use App\Enums\Activity\ActivityEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tool\IndexToolRequest;
use App\Http\Requests\Tool\StoreToolRequest;
use App\Http\Requests\Tool\UpdateToolRequest;
use App\Http\Resources\Tool\ToolResource;
use App\Models\Tool\Tool;
use App\Models\User\User;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ToolController extends Controller
{
    /**
     * Number of tools per page.
     */
    private const int PER_PAGE = 12;

    /**
     * Relations shown with each tool.
     *
     * @var list<string>
     */
    private const array RELATIONS = ['user:id,name', 'categories', 'tags', 'companyRoles', 'images'];

    /**
     * List tools, filtered by name, company role, category and tag, with
     * `favorites` only the signed-in user's favorite tools and with `mine`
     * only the tools the signed-in user added. Pending tools are listed only
     * for their creator.
     *
     * The most favorited tools come first, then by name; the user's own
     * favorites (`favorites`) stay newest first.
     */
    public function index(IndexToolRequest $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $tools = Tool::query()
            ->with(self::RELATIONS)
            ->withFavoriteInfo($user)
            ->visibleTo($user)
            ->filter($request->validated())
            ->when($request->boolean('favorites'), fn ($query) => $query->favoriteOf($user))
            ->when($request->boolean('mine'), fn ($query) => $query->whereBelongsTo($user))
            ->when(
                $request->boolean('favorites'),
                fn ($query) => $query->latest()->latest('id'),
                fn ($query) => $query->orderByDesc('favorites_count')->orderBy('name')->orderBy('id'),
            )
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return ToolResource::collection($tools);
    }

    /**
     * Show a tool, found by its slug. A pending tool only to its creator and admins.
     */
    public function show(Request $request, Tool $tool): ToolResource
    {
        Gate::authorize('view', $tool);

        return $this->toolResource($tool, $request->user());
    }

    /**
     * List the tools waiting for an admin, oldest first.
     */
    public function pending(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewPending', Tool::class);

        $tools = Tool::pending()
            ->with(self::RELATIONS)
            ->withFavoriteInfo($request->user())
            ->oldest()
            ->oldest('id')
            ->get();

        return ToolResource::collection($tools);
    }

    /**
     * Approve a pending tool, so everybody can see it.
     */
    public function approve(Request $request, Tool $tool, ActivityLogger $activity): ToolResource|JsonResponse
    {
        Gate::authorize('approve', $tool);

        if ($tool->isApproved()) {
            return $this->alreadyApproved();
        }

        DB::transaction(function () use ($tool, $activity) {
            $tool->approved_at = now();
            $tool->save();

            $activity->log(ActivityEvent::ToolApproved, $tool, properties: ['creator' => $tool->user()->value('name')]);
        });

        return $this->toolResource($tool, $request->user());
    }

    /**
     * Reject a pending tool: it is deleted (soft, like any deleted tool).
     */
    public function reject(Tool $tool, ActivityLogger $activity): JsonResponse|Response
    {
        Gate::authorize('reject', $tool);

        if ($tool->isApproved()) {
            return $this->alreadyApproved();
        }

        DB::transaction(function () use ($tool, $activity) {
            $activity->log(ActivityEvent::ToolRejected, $tool, properties: ['creator' => $tool->user()->value('name')]);

            $tool->delete();
        });

        return response()->noContent();
    }

    /**
     * Add a tool created by the signed-in user.
     */
    public function store(StoreToolRequest $request, CreateTool $createTool): JsonResponse
    {
        $tool = $createTool->handle($request->user(), $request->validated());

        return $this->toolResource($tool, $request->user())->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Replace a tool's data.
     */
    public function update(UpdateToolRequest $request, Tool $tool, UpdateTool $updateTool): ToolResource
    {
        $tool = $updateTool->handle($tool, $request->validated());

        return $this->toolResource($tool, $request->user());
    }

    /**
     * Soft delete a tool.
     */
    public function destroy(Tool $tool, ActivityLogger $activity): Response
    {
        Gate::authorize('delete', $tool);

        DB::transaction(function () use ($tool, $activity) {
            $activity->log(ActivityEvent::ToolDeleted, $tool, properties: ['creator' => $tool->user()->value('name')]);

            $tool->delete();
        });

        return response()->noContent();
    }

    /**
     * Approved tools are not pending any more and cannot be decided on again.
     */
    private function alreadyApproved(): JsonResponse
    {
        return response()->json([
            'message' => 'The tool is already approved.',
        ], Response::HTTP_CONFLICT);
    }

    /**
     * The tool with its relations and the user's favorite info.
     */
    private function toolResource(Tool $tool, User $user): ToolResource
    {
        return new ToolResource($tool->load(self::RELATIONS)->loadFavoriteInfo($user));
    }
}
