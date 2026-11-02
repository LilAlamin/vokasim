<?php

namespace Database\Factories;

use App\Models\IndustryEvaluation;
use App\Models\InternshipPlacement;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<IndustryEvaluation> */
class IndustryEvaluationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'internship_placement_id' => InternshipPlacement::factory(),
            'mentor_id' => fn (array $attributes): int => InternshipPlacement::query()->whereKey($attributes['internship_placement_id'])->firstOrFail()->mentor_id,
            'period_type' => 'monthly',
            'period_month' => today()->startOfMonth()->toDateString(),
            'work_quality' => 4,
            'tool_mastery' => 4,
            'discipline' => 4,
            'teamwork' => 4,
            'professional_ethics' => 4,
            'final_score' => 80,
            'feedback' => 'Mampu menyelesaikan tugas secara mandiri dan konsisten.',
            'improvement_plan' => 'Tingkatkan dokumentasi dan komunikasi progres pekerjaan.',
            'revision' => 1,
        ];
    }
}
