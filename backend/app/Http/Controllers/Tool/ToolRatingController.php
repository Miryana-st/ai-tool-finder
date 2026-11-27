<?php

namespace App\Http\Controllers\Tool;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tool\RateToolRequest;
use App\Models\Tool\Tool;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The signed-in user's rating (1 to 5 stars) of a tool. Each user has at most
 * one rating per tool; both actions can be repeated safely.
 */
class ToolRatingController extends Controller
{
    /**
     * Rate the tool, or change the signed-in user's rating.
     */
    public function update(RateToolRequest $request, Tool $tool): JsonResponse
    {
        $tool->raters()->syncWithoutDetaching([
            $request->user()->id => ['rating' => $request->integer('rating')],
        ]);

        return $this->ratingInfo($request, $tool);
    }

    /**
     * Remove the signed-in user's rating of the tool.
     */
    public function destroy(Request $request, Tool $tool): JsonResponse
    {
        $tool->raters()->detach($request->user()->id);

        return $this->ratingInfo($request, $tool);
    }

    /**
     * The tool's current rating info for the signed-in user.
     */
    private function ratingInfo(Request $request, Tool $tool): JsonResponse
    {
        $tool->loadRatingInfo($request->user());

        return response()->json([
            'data' => [
                'rating_average' => $tool->rating_average === null ? null : round((float) $tool->rating_average, 1),
                'ratings_count' => (int) $tool->ratings_count,
                'my_rating' => $tool->my_rating === null ? null : (int) $tool->my_rating,
            ],
        ]);
    }
}
