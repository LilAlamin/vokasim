<?php

namespace App\Policies;

use App\Models\PracticumSubmission;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class PracticumSubmissionPolicy
{
    public function view(User $user, PracticumSubmission $submission): bool
    {
        return $this->assess($user, $submission)
            || ($user->isMahasiswa() && $submission->student_id === $user->id && Gate::forUser($user)->allows('view', $submission->assignment->module));
    }

    public function assess(User $user, PracticumSubmission $submission): bool
    {
        return Gate::forUser($user)->allows('manage', $submission->assignment->module);
    }
}
