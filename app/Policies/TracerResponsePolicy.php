<?php

namespace App\Policies;

use App\Models\TracerResponse;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TracerResponsePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isMahasiswa();
    }

    public function view(User $user, TracerResponse $response): Response
    {
        return $user->isAdmin() || ($user->isMahasiswa() && $response->user_id === $user->id)
            ? Response::allow() : Response::denyAsNotFound();
    }

    public function update(User $user, TracerResponse $response): bool
    {
        return $user->isMahasiswa() && $response->user_id === $user->id;
    }
}
