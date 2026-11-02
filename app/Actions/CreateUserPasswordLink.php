<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class CreateUserPasswordLink
{
    public function handle(User $actor, int $userId): string
    {
        return DB::transaction(function () use ($actor, $userId): string {
            $user = User::query()->lockForUpdate()->findOrFail($userId);
            Gate::forUser($actor)->authorize('update', $user);

            if (! $user->is_active) {
                throw ValidationException::withMessages(['account' => 'Aktifkan akun sebelum membuat tautan pengaturan sandi.']);
            }

            $broker = Password::broker(config('fortify.passwords'));

            if ($broker->getRepository()->recentlyCreatedToken($user)) {
                throw ValidationException::withMessages(['account' => 'Tautan baru saja dibuat. Tunggu sebentar sebelum membuat tautan pengganti.']);
            }

            $token = $broker->createToken($user);

            return route('password.reset', ['token' => $token, 'email' => $user->email]);
        });
    }
}
