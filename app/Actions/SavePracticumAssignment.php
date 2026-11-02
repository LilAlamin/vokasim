<?php

namespace App\Actions;

use App\Models\PracticumAssignment;
use App\Models\PracticumModule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SavePracticumAssignment
{
    /** @param array<string, mixed> $input */
    public function handle(User $user, int $moduleId, array $input, ?int $assignmentId = null): PracticumAssignment
    {
        return DB::transaction(function () use ($user, $moduleId, $input, $assignmentId): PracticumAssignment {
            $module = PracticumModule::query()->forUser($user)->lockForUpdate()->findOrFail($moduleId);
            Gate::forUser($user)->authorize('manage', $module);
            $assignment = $assignmentId ? $module->assignments()->lockForUpdate()->findOrFail($assignmentId) : new PracticumAssignment(['practicum_module_id' => $module->id]);
            $data = Validator::make($input, [
                'title' => ['required', 'string', 'max:255'],
                'instructions' => ['required', 'string', 'max:20000'],
                'due_at' => ['required', 'date_format:Y-m-d\TH:i'],
                'weight' => ['required', 'numeric', 'between:0.01,100', 'decimal:0,2'],
                'allow_late' => ['required', 'boolean'],
                'criteria' => ['required', 'array', 'min:2', 'max:20'],
                'criteria.*' => ['required', 'array:title,category,max_score,weight'],
                'criteria.*.title' => ['required', 'string', 'max:255'],
                'criteria.*.category' => ['required', Rule::in(['hard_skill', 'soft_skill'])],
                'criteria.*.max_score' => ['required', 'numeric', 'between:0.01,100', 'decimal:0,2'],
                'criteria.*.weight' => ['required', 'numeric', 'between:0.01,100', 'decimal:0,2'],
            ])->validate();

            if (round(array_sum(array_column($data['criteria'], 'weight')), 2) !== 100.0 || count(array_unique(array_column($data['criteria'], 'category'))) !== 2) {
                throw ValidationException::withMessages(['criteria' => 'Rubrik harus memuat hard skill dan soft skill dengan total bobot tepat 100%.']);
            }

            $dueAt = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data['due_at'], $module->timezone)->utc();

            if ($dueAt->setTimezone($module->timezone)->toDateString() < $module->starts_on->toDateString()) {
                throw ValidationException::withMessages(['due_at' => 'Tenggat tugas tidak boleh sebelum periode kelas dimulai.']);
            }

            if ($assignment->exists && $assignment->submissions()->exists()) {
                $criteria = $assignment->rubricCriteria->map(fn ($criterion): array => ['title' => $criterion->title, 'category' => $criterion->category, 'max_score' => $criterion->max_score, 'weight' => $criterion->weight])->all();

                if ($criteria != $data['criteria'] || $assignment->weight !== (float) $data['weight']) {
                    throw ValidationException::withMessages(['criteria' => 'Rubrik dan bobot tugas terkunci setelah laporan pertama dikirim.']);
                }
            } else {
                $assignment->fill(['title' => $data['title'], 'instructions' => $data['instructions'], 'due_at' => $dueAt, 'weight' => $data['weight'], 'allow_late' => $data['allow_late']])->save();
                $assignment->rubricCriteria()->delete();

                foreach (array_values($data['criteria']) as $position => $criterion) {
                    $assignment->rubricCriteria()->create([...$criterion, 'position' => $position]);
                }

                return $assignment;
            }

            $assignment->fill(['title' => $data['title'], 'instructions' => $data['instructions'], 'due_at' => $dueAt, 'allow_late' => $data['allow_late']])->save();

            return $assignment;
        });
    }
}
