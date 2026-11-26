<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\User\UserResource;
use App\Models\User\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;

class UserTwoFactorController extends Controller
{
    /**
     * Turn off the given user's two-factor authentication (admins only), so they
     * can sign in with the password and set it up again. Safe to repeat.
     */
    public function destroy(User $user, DisableTwoFactorAuthentication $disable): UserResource
    {
        Gate::authorize('resetTwoFactor', $user);

        $disable($user);

        return new UserResource($user->loadMissing('companyRole'));
    }
}
