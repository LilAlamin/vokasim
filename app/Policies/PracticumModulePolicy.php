<?php

namespace App\Policies;

use App\Models\PracticumModule;
use App\Models\User;

class PracticumModulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isDosen() || $user->isMahasiswa();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isDosen();
    }

    public function manage(User $user, PracticumModule $module): bool
    {
        return $user->isAdmin() || ($user->isDosen() && $module->lecturer_id === $user->id);
    }

    public function view(User $user, PracticumModule $module): bool
    {
        return $this->manage($user, $module) || $this->participate($user, $module);
    }

    public function participate(User $user, PracticumModule $module): bool
    {
        return $user->isMahasiswa() && $module->published
            && $module->enrollments()->where('student_id', $user->id)->where('status', 'active')->exists();
    }
}
