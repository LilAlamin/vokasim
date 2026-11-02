<?php

namespace Database\Factories;

use App\Models\PracticumAssignment;
use App\Models\PracticumRubricCriterion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PracticumRubricCriterion> */
class PracticumRubricCriterionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['practicum_assignment_id' => PracticumAssignment::factory(), 'title' => 'Ketepatan hasil kerja', 'category' => 'hard_skill', 'max_score' => 100, 'weight' => 50, 'position' => 0];
    }
}
