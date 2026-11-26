<?php

namespace App\Http\Controllers\Activity;

use App\Enums\Activity\ActivityEvent;
use App\Enums\Activity\ActivityGroup;
use App\Http\Controllers\Controller;
use App\Http\Requests\Activity\IndexActivityLogRequest;
use App\Http\Resources\Activity\ActivityLogResource;
use App\Models\Activity\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * The activity history of all users (admins only): who did what, when, and
 * what changed. Entries are written by App\Services\ActivityLogger.
 */
class ActivityLogController extends Controller
{
    /**
     * Number of entries per page.
     */
    private const int PER_PAGE = 10;

    /**
     * List entries, newest first, filtered by user, group, event, subject and time.
     */
    public function index(IndexActivityLogRequest $request): AnonymousResourceCollection
    {
        $entries = ActivityLog::query()
            ->filter($request->validated())
            ->latest()
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return ActivityLogResource::collection($entries);
    }

    /**
     * The groups and their events with labels, for the filters.
     */
    public function events(): JsonResponse
    {
        Gate::authorize('viewAny', ActivityLog::class);

        return response()->json([
            'data' => array_map(fn (ActivityGroup $group) => [
                'value' => $group->value,
                'label' => $group->label(),
                'events' => array_map(fn (ActivityEvent $event) => [
                    'value' => $event->value,
                    'label' => $event->label(),
                ], $group->events()),
            ], ActivityGroup::cases()),
        ]);
    }
}
