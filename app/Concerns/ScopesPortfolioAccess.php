<?php

namespace App\Concerns;

use App\Models\InternshipPlacement;
use App\Models\PracticumEnrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

trait ScopesPortfolioAccess
{
    /** @param Builder<static> $query */
    #[Scope]
    protected function forUser(Builder $query, User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }

        if ($user->isMahasiswa()) {
            $query->where('student_id', $user->id);

            return;
        }

        if (! $user->isDosen()) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function (Builder $students) use ($user): void {
            $students->whereIn('student_id', InternshipPlacement::query()->select('student_id')->where('lecturer_id', $user->id))
                ->orWhereIn('student_id', PracticumEnrollment::query()->select('student_id')->where('status', 'active')
                    ->whereHas('module', fn (Builder $modules) => $modules->where('lecturer_id', $user->id)));
        });
    }
}
