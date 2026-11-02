<?php

namespace App\Actions;

use App\Models\InternshipPlacement;
use App\Models\InternshipProject;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SaveInternshipProject
{
    /** @param array<string, mixed> $input */
    public function handle(User $user, int $placementId, array $input, ?int $projectId = null): InternshipProject
    {
        return DB::transaction(function () use ($user, $placementId, $input, $projectId): InternshipProject {
            $placement = InternshipPlacement::query()->forUser($user)->lockForUpdate()->findOrFail($placementId);
            Gate::forUser($user)->authorize('create', [InternshipProject::class, $placement]);
            $project = $projectId ? $placement->projects()->lockForUpdate()->findOrFail($projectId) : new InternshipProject;

            if ($project->exists) {
                $project->setRelation('placement', $placement);
                Gate::forUser($user)->authorize('update', $project);
            }

            $data = Validator::make($input, [
                'title' => ['required', 'string', 'max:255'],
                'description' => ['required', 'string', 'min:10', 'max:3000'],
                'starts_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$placement->starts_on->toDateString()],
                'due_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on', 'before_or_equal:'.$placement->ends_on->toDateString()],
                'target_minutes' => ['required', 'integer', 'between:1,300000'],
                'completed_minutes' => ['required', 'integer', 'min:0', 'lte:target_minutes'],
                'feedback' => ['nullable', 'string', 'max:2000'],
            ], [
                'completed_minutes.lte' => 'Capaian tidak boleh melebihi target proyek.',
                'starts_on.after_or_equal' => 'Tanggal proyek harus berada dalam periode magang.',
                'due_on.before_or_equal' => 'Tenggat proyek harus berada dalam periode magang.',
            ])->validate();

            if ((int) $data['completed_minutes'] > 0 && $data['starts_on'] > now($placement->timezone)->toDateString()) {
                throw ValidationException::withMessages(['completed_minutes' => 'Capaian hanya dapat dicatat setelah proyek dimulai.']);
            }

            $project->fill([...$data, 'internship_placement_id' => $placement->id, 'mentor_id' => $user->id])->save();

            return $project;
        });
    }
}
