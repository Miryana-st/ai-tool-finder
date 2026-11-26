<?php

namespace App\Http\Controllers\User;

use App\Actions\User\CreateUser;
use App\Actions\User\DeleteUser;
use App\Actions\User\UpdateUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\IndexUserRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\User\UserResource;
use App\Models\User\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    /**
     * Number of users per page.
     */
    private const int PER_PAGE = 10;

    /**
     * List users by name, filtered by name or email, system role and company role (admins only).
     */
    public function index(IndexUserRequest $request): AnonymousResourceCollection
    {
        $users = User::query()
            ->with('companyRole')
            ->filter($request->validated())
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return UserResource::collection($users);
    }

    /**
     * Create a user account (admins only).
     */
    public function store(StoreUserRequest $request, CreateUser $createUser): JsonResponse
    {
        $user = $createUser->handle($request->validated());

        return (new UserResource($user->load('companyRole')))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Show the given user (admins only), e.g. for the edit page.
     */
    public function show(User $user): UserResource
    {
        Gate::authorize('view', $user);

        return new UserResource($user->load('companyRole'));
    }

    /**
     * Change the given user's name, email, company role and, optionally,
     * password (admins only).
     */
    public function update(UpdateUserRequest $request, User $user, UpdateUser $updateUser): UserResource
    {
        $isSelf = $request->user()->is($user);

        $user = $updateUser->handle(
            $user,
            $request->validated(),
            $isSelf && $request->hasSession() ? $request->session()->getId() : null,
        );

        // Sanctum keeps the signed-in user's password hash in the session; reload
        // the user so a new password of their own does not sign them out.
        if ($isSelf) {
            $request->user()->refresh();
        }

        return new UserResource($user->load('companyRole'));
    }

    /**
     * Delete the given user (admins only, not themselves).
     */
    public function destroy(User $user, DeleteUser $deleteUser): JsonResponse|Response
    {
        Gate::authorize('delete', $user);

        if (! $deleteUser->handle($user)) {
            return response()->json([
                'message' => 'The user has added tools and cannot be deleted.',
            ], Response::HTTP_CONFLICT);
        }

        return response()->noContent();
    }
}
