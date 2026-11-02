<?php

namespace App\Actions;

use App\Models\IndustryEvaluation;
use App\Models\InternshipPlacement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaveIndustryEvaluation
{
    /** @param array<string, mixed> $input */
    public function handle(User $user, int $placementId, array $input): IndustryEvaluation
    {
        return DB::transaction(function () use ($user, $placementId, $input): IndustryEvaluation {
            $placement = InternshipPlacement::query()->forUser($user)->lockForUpdate()->findOrFail($placementId);
            Gate::forUser($user)->authorize('create', [IndustryEvaluation::class, $placement]);
            $rules = [
                'period_type' => ['required', Rule::in(['monthly', 'final'])],
                'period_month' => ['exclude_unless:period_type,monthly', 'required', 'date_format:Y-m'],
                'feedback' => ['required', 'string', 'min:10', 'max:3000'],
                'improvement_plan' => ['required', 'string', 'min:10', 'max:3000'],
            ];

            foreach (array_keys(IndustryEvaluation::rubric()) as $criterion) {
                $rules[$criterion] = ['required', 'integer', 'between:1,5'];
            }

            $data = Validator::make($input, $rules, [
                'required' => ':attribute wajib diisi.',
                'between' => 'Pilih skor rubrik antara 1 dan 5.',
                'feedback.min' => 'Berikan umpan balik spesifik setidaknya 10 karakter.',
                'improvement_plan.min' => 'Jelaskan langkah perbaikan setidaknya 10 karakter.',
            ])->validate();
            $today = CarbonImmutable::now($placement->timezone)->startOfDay();

            if ($data['period_type'] === 'final') {
                if ($placement->ends_on->toDateString() > $today->toDateString()) {
                    throw ValidationException::withMessages(['period_type' => 'Evaluasi akhir tersedia setelah tanggal akhir magang tercapai.']);
                }

                $month = $placement->ends_on->startOfMonth();
            } else {
                $month = CarbonImmutable::parse($data['period_month'].'-01');

                if ($month->format('Y-m') < $placement->starts_on->format('Y-m') || $month->format('Y-m') > $placement->ends_on->format('Y-m') || $month->format('Y-m') > $today->format('Y-m') || $placement->starts_on->toDateString() > $today->toDateString()) {
                    throw ValidationException::withMessages(['period_month' => 'Pilih bulan magang yang sudah dimulai dan berada dalam periode penempatan.']);
                }
            }

            $evaluation = $placement->evaluations()->where('period_type', $data['period_type'])
                ->whereDate('period_month', $month->toDateString())->lockForUpdate()->first() ?? new IndustryEvaluation;

            if ($evaluation->exists) {
                $evaluation->setRelation('placement', $placement);
                Gate::forUser($user)->authorize('update', $evaluation);
            }

            $score = 0;
            foreach (array_keys(IndustryEvaluation::rubric()) as $criterion) {
                $score += (int) $data[$criterion];
            }

            $evaluation->fill([
                ...$data,
                'internship_placement_id' => $placement->id,
                'mentor_id' => $user->id,
                'period_month' => $month->toDateString(),
                'final_score' => $score * 4,
                'revision' => $evaluation->exists ? $evaluation->revision + 1 : 1,
            ])->save();

            return $evaluation;
        });
    }
}
