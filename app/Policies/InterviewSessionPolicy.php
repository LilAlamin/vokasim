<?php

namespace App\Policies;

use App\Enums\InterviewStatus;
use App\Models\InterviewSession;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class InterviewSessionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isMahasiswa() || $user->isDosen();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, InterviewSession $interviewSession): Response
    {
        return InterviewSession::query()->forUser($user)->whereKey($interviewSession->id)->exists()
            ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isMahasiswa();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, InterviewSession $interviewSession): bool
    {
        return $user->isMahasiswa() && $interviewSession->student_id === $user->id && $interviewSession->status === InterviewStatus::Active;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, InterviewSession $interviewSession): bool
    {
        return $user->isMahasiswa() && $interviewSession->student_id === $user->id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function manage(User $user, InterviewSession $interviewSession): bool
    {
        return $user->isMahasiswa() && $interviewSession->student_id === $user->id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function review(User $user, InterviewSession $interviewSession): bool
    {
        return $user->isDosen() && $interviewSession->status === InterviewStatus::Completed
            && $this->view($user, $interviewSession)->allowed();
    }
}
