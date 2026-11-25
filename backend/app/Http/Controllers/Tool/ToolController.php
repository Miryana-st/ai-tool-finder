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
use Illuminate\Http\JsonResponse;
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
    private const array RELATIONS = ['user:id,name', 'categories', 'tags', 'companyRoles'];

    /**
     * List tools, newest first, filtered by name, company role, category and tag.
     */
    public function index(IndexToolRequest $request): AnonymousResourceCollection
    {
        $tools = Tool::query()
            ->with(self::RELATIONS)
            ->filter($request->validated())
            ->latest()
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return ToolResource::collection($tools);
    }

    /**
     * Show a tool, found by its slug.
     */
    public function show(Tool $tool): ToolResource
    {
        return new ToolResource($tool->load(self::RELATIONS));
    }

    /**
     * Add a tool created by the signed-in user.
     */
    public function store(StoreToolRequest $request, CreateTool $createTool): JsonResponse
    {
        $tool = $createTool->handle($request->user(), $request->validated());

        return (new ToolResource($tool->load(self::RELATIONS)))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Replace a tool's data.
     */
    public function update(UpdateToolRequest $request, Tool $tool, UpdateTool $updateTool): ToolResource
    {
        $tool = $updateTool->handle($tool, $request->validated());

        return new ToolResource($tool->load(self::RELATIONS));
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
}
