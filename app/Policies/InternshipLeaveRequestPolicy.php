<?php

namespace App\Policies;

use App\Models\InternshipLeaveRequest;
use App\Models\InternshipPlacement;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class InternshipLeaveRequestPolicy
{
    public function view(User $user, InternshipLeaveRequest $leave): Response
    {
        return (new InternshipPlacementPolicy)->view($user, $leave->placement)
            ? Response::allow() : Response::denyAsNotFound();
    }

    public function create(User $user, InternshipPlacement $placement): bool
    {
        return $user->isMahasiswa() && $placement->student_id === $user->id;
    }

    public function review(User $user, InternshipLeaveRequest $leave): bool
    {
        return $user->isMentorDudi() && $leave->placement->mentor_id === $user->id;
    }

    public function viewEvidence(User $user, InternshipLeaveRequest $leave): Response
    {
        return $user->isAdmin()
            || ($user->isMahasiswa() && $leave->placement->student_id === $user->id)
            || ($user->isMentorDudi() && $leave->placement->mentor_id === $user->id)
            ? Response::allow() : Response::denyAsNotFound();
    }
}
