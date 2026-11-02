<?php

namespace App\Actions;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;

class DeleteManagedUser
{
    public function __construct(private RevokeUserSessions $revokeSessions) {}

    public function handle(User $actor, int $userId): void
    {
        Gate::forUser($actor)->authorize('viewAny', User::class);
        DB::transaction(function () use ($actor, $userId): void {
            User::query()->where('role', UserRole::Admin)->where('is_active', true)->orderBy('id')->lockForUpdate()->get(['id']);
            $currentActor = User::query()->findOrFail($actor->id);
            $user = User::query()->lockForUpdate()->findOrFail($userId);
            Gate::forUser($currentActor)->authorize('delete', $user);
            $user->delete();
            $this->revokeSessions->deleteStoredSessions($user);
            Password::broker(config('fortify.passwords'))->deleteToken($user);
        });
    }
}
