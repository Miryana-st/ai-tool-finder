<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\IndexUserRequest;
use App\Http\Resources\User\UserResource;
use App\Models\User\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    /**
     * Number of users per page.
     */
    private const int PER_PAGE = 20;

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
}
