<?php

namespace App\Http\Controllers\Tool;

use App\Http\Controllers\Controller;
use App\Models\Tool\Tool;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The signed-in user's favorite tools. Every user may mark any tool, and both
 * actions can be repeated safely.
 */
class FavoriteToolController extends Controller
{
    /**
     * Mark the tool as a favorite of the signed-in user.
     */
    public function store(Request $request, Tool $tool): JsonResponse
    {
        $request->user()->favoriteTools()->syncWithoutDetaching([$tool->id]);

        return $this->favoriteInfo($request, $tool);
    }

    /**
     * Remove the tool from the signed-in user's favorites.
     */
    public function destroy(Request $request, Tool $tool): JsonResponse
    {
        $request->user()->favoriteTools()->detach($tool->id);

        return $this->favoriteInfo($request, $tool);
    }

    /**
     * The tool's current favorite info for the signed-in user.
     */
    private function favoriteInfo(Request $request, Tool $tool): JsonResponse
    {
        $tool->loadFavoriteInfo($request->user());

        return response()->json([
            'data' => [
                'is_favorite' => (bool) $tool->is_favorite,
                'favorites_count' => (int) $tool->favorites_count,
            ],
        ]);
    }
}
