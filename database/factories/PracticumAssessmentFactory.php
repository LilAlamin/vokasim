<?php

namespace Database\Factories;

use App\Models\PracticumAssessment;
use App\Models\PracticumSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PracticumAssessment> */
class PracticumAssessmentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'practicum_submission_id' => PracticumSubmission::factory()->graded(),
            'assessor_id' => fn (array $attributes): int => PracticumSubmission::query()->whereKey($attributes['practicum_submission_id'])->firstOrFail()->assignment->module->lecturer_id,
            'scores' => fn (array $attributes): array => PracticumSubmission::query()->whereKey($attributes['practicum_submission_id'])->firstOrFail()->assignment->rubricCriteria->map(fn ($criterion): array => [
                'id' => $criterion->id, 'title' => $criterion->title, 'category' => $criterion->category,
                'weight' => $criterion->weight, 'max_score' => $criterion->max_score, 'score' => $criterion->max_score * 0.8,
            ])->all(),
            'hard_skill_score' => 80, 'soft_skill_score' => 80, 'final_score' => 80,
            'feedback' => 'Prosedur dan hasil sudah baik. Tingkatkan ketelitian dokumentasi.', 'assessed_at' => now('UTC'),
        ];
    }
}
