<?php

namespace App\Http\Controllers;

use App\Http\Resources\User\UserResource;
use App\Services\DashboardMenu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Return the authenticated user's dashboard data.
     */
    public function __invoke(Request $request, DashboardMenu $menu): JsonResponse
    {
        $user = $request->user()->loadMissing('companyRole');

        return response()->json([
            'data' => [
                'user' => (new UserResource($user))->resolve($request),
                'menu' => $menu->for($user),
            ],
        ]);
    }
}
