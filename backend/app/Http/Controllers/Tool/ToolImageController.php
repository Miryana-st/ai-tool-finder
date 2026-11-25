<?php

namespace App\Http\Controllers\Tool;

use App\Actions\Tool\AddToolImage;
use App\Actions\Tool\DeleteToolImage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tool\StoreToolImageRequest;
use App\Http\Resources\Tool\ToolImageResource;
use App\Models\Tool\Tool;
use App\Models\Tool\ToolImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Screenshots of a tool. The same users who may edit the tool may manage them.
 */
class ToolImageController extends Controller
{
    /**
     * Upload a screenshot for the tool.
     */
    public function store(StoreToolImageRequest $request, Tool $tool, AddToolImage $addToolImage): JsonResponse
    {
        $image = $addToolImage->handle($tool, $request->file('image'));

        return (new ToolImageResource($image))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Delete a screenshot of the tool and its file.
     */
    public function destroy(Tool $tool, ToolImage $image, DeleteToolImage $deleteToolImage): Response
    {
        Gate::authorize('update', $tool);

        $deleteToolImage->handle($image);

        return response()->noContent();
    }
}
