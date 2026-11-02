<?php

namespace App\Policies;

use App\Models\InternshipPlacement;
use App\Models\User;

class InternshipPlacementPolicy
{
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, InternshipPlacement $placement): bool
    {
        return $user->isAdmin()
            || ($user->isMahasiswa() && $placement->student_id === $user->id)
            || ($user->isMentorDudi() && $placement->mentor_id === $user->id)
            || ($user->isDosen() && $placement->lecturer_id === $user->id);
    }

    public function attend(User $user, InternshipPlacement $placement): bool
    {
        return $user->isMahasiswa() && $placement->student_id === $user->id;
    }
}
