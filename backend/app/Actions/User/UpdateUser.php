<?php

namespace App\Actions\User;

use App\Models\User\CompanyRole;
use App\Models\User\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UpdateUser
{
    /**
     * Change the user's details on behalf of an admin.
     *
     * A new password signs the user out everywhere (sessions and "remember me"),
     * except the current session when admins change their own password here.
     *
     * @param  array{name: string, email: string, company_role: string, password?: ?string}  $data
     */
    public function handle(User $user, array $data, ?string $currentSessionId = null): User
    {
        return DB::transaction(function () use ($user, $data, $currentSessionId) {
            $user->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'company_role_id' => CompanyRole::where('slug', $data['company_role'])->value('id'),
            ]);

            if (filled($data['password'] ?? null)) {
                $user->password = Hash::make($data['password']);
                $user->setRememberToken(Str::random(60));

                DB::table('sessions')
                    ->where('user_id', $user->id)
                    ->when($currentSessionId, fn ($query, string $id) => $query->where('id', '!=', $id))
                    ->delete();
            }

            $user->save();

            return $user;
        });
    }
}
