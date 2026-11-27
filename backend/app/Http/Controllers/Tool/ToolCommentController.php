<?php

namespace App\Http\Controllers\Tool;

use App\Actions\Tool\DeleteToolComment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tool\StoreToolCommentRequest;
use App\Http\Requests\Tool\UpdateToolCommentRequest;
use App\Http\Resources\Tool\ToolCommentResource;
use App\Models\Tool\Tool;
use App\Models\Tool\ToolComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ToolCommentController extends Controller
{
    /**
     * Number of comments per page.
     */
    private const int PER_PAGE = 10;

    /**
     * List the comments on a tool, newest first.
     */
    public function index(Tool $tool): AnonymousResourceCollection
    {
        Gate::authorize('view', $tool);

        $comments = $tool->comments()
            ->with('user:id,name')
            ->latest()
            ->latest('id')
            ->paginate(self::PER_PAGE);

        return ToolCommentResource::collection($comments);
    }

    /**
     * Add a comment by the signed-in user.
     */
    public function store(StoreToolCommentRequest $request, Tool $tool): JsonResponse
    {
        $comment = $tool->comments()->make($request->validated());
        $comment->user()->associate($request->user());
        $comment->save();

        return (new ToolCommentResource($comment->load('user:id,name')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Change the text of the signed-in user's own comment.
     */
    public function update(UpdateToolCommentRequest $request, Tool $tool, ToolComment $comment): ToolCommentResource
    {
        $comment->update($request->validated());

        return new ToolCommentResource($comment->load('user:id,name'));
    }

    /**
     * Delete a comment: the author's own, or any comment by an admin.
     */
    public function destroy(Request $request, Tool $tool, ToolComment $comment, DeleteToolComment $deleteComment): Response
    {
        Gate::authorize('delete', $comment);

        $deleteComment->handle($comment, $request->user());

        return response()->noContent();
    }
}
