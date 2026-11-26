<?php

namespace App\Http\Controllers\Tool;

use App\Actions\Tool\CreateTool;
use App\Actions\Tool\UpdateTool;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tool\IndexToolRequest;
use App\Http\Requests\Tool\StoreToolRequest;
use App\Http\Requests\Tool\UpdateToolRequest;
use App\Http\Resources\Tool\ToolResource;
use App\Models\Tool\Tool;
use App\Models\User\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
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
     * List tools, newest first, filtered by name, company role, category and tag,
     * with `favorites` only the signed-in user's favorite tools and with `mine`
     * only the tools the signed-in user added.
     */
    public function index(IndexToolRequest $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $tools = Tool::query()
            ->with(self::RELATIONS)
            ->withFavoriteInfo($user)
            ->filter($request->validated())
            ->when($request->boolean('favorites'), fn ($query) => $query->favoriteOf($user))
            ->when($request->boolean('mine'), fn ($query) => $query->whereBelongsTo($user))
            ->latest()
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return ToolResource::collection($tools);
    }

    /**
     * Show a tool, found by its slug.
     */
    public function show(Request $request, Tool $tool): ToolResource
    {
        return $this->toolResource($tool, $request->user());
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
    public function destroy(Tool $tool): Response
    {
        Gate::authorize('delete', $tool);

        $tool->delete();

        return response()->noContent();
    }

    /**
     * The tool with its relations and the user's favorite info.
     */
    private function toolResource(Tool $tool, User $user): ToolResource
    {
        return new ToolResource($tool->load(self::RELATIONS)->loadFavoriteInfo($user));
    }
}
