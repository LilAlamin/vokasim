<?php

namespace App\Policies;

use App\Enums\LogbookStatus;
use App\Models\InternshipLogbook;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class InternshipLogbookPolicy
{
    public function view(User $user, InternshipLogbook $logbook): Response
    {
        return (new InternshipPlacementPolicy)->view($user, $logbook->placement)
            ? Response::allow() : Response::denyAsNotFound();
    }

    public function update(User $user, InternshipLogbook $logbook): bool
    {
        return $user->isMahasiswa() && $logbook->placement->student_id === $user->id
            && $logbook->status === LogbookStatus::Draft;
    }

    public function review(User $user, InternshipLogbook $logbook): bool
    {
        return ($user->isMentorDudi() && $logbook->placement->mentor_id === $user->id && $logbook->status === LogbookStatus::PendingMentor)
            || ($user->isDosen() && $logbook->placement->lecturer_id === $user->id && $logbook->status === LogbookStatus::MentorApproved);
    }
}
