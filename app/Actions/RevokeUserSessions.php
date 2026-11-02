<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class RevokeUserSessions
{
    public function handle(User $user): void
    {
        $user->forceFill(['auth_version' => $user->auth_version + 1, 'remember_token' => Str::random(60)])->save();
        $this->deleteStoredSessions($user);
        Password::broker(config('fortify.passwords'))->deleteToken($user);
    }

    public function deleteStoredSessions(User $user): void
    {
        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
    }
}
