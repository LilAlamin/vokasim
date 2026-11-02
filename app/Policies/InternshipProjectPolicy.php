<?php

namespace App\Policies;

use App\Models\InternshipPlacement;
use App\Models\InternshipProject;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class InternshipProjectPolicy
{
    public function view(User $user, InternshipProject $project): Response
    {
        return (new InternshipPlacementPolicy)->view($user, $project->placement)
            ? Response::allow() : Response::denyAsNotFound();
    }

    public function create(User $user, InternshipPlacement $placement): bool
    {
        return $user->isMentorDudi() && $placement->mentor_id === $user->id;
    }

    public function update(User $user, InternshipProject $project): bool
    {
        return $this->create($user, $project->placement);
    }
}
