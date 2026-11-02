<?php

namespace App\Policies;

use App\Models\IndustryEvaluation;
use App\Models\InternshipPlacement;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class IndustryEvaluationPolicy
{
    public function view(User $user, IndustryEvaluation $evaluation): Response
    {
        return (new InternshipPlacementPolicy)->view($user, $evaluation->placement)
            ? Response::allow() : Response::denyAsNotFound();
    }

    public function create(User $user, InternshipPlacement $placement): bool
    {
        return $user->isMentorDudi() && $placement->mentor_id === $user->id;
    }

    public function update(User $user, IndustryEvaluation $evaluation): bool
    {
        return $this->create($user, $evaluation->placement);
    }
}
