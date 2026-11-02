<?php

namespace Database\Factories;

use App\Models\PracticumAssignment;
use App\Models\PracticumModule;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PracticumAssignment> */
class PracticumAssignmentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'practicum_module_id' => PracticumModule::factory(), 'title' => 'Laporan pengukuran komponen',
            'instructions' => 'Dokumentasikan prosedur, hasil pengukuran, analisis kesalahan, dan penerapan K3.',
            'due_at' => now('UTC')->addWeek(), 'weight' => 1, 'allow_late' => false,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (PracticumAssignment $assignment): void {
            $assignment->rubricCriteria()->createMany([
                ['title' => 'Ketepatan prosedur dan hasil', 'category' => 'hard_skill', 'max_score' => 100, 'weight' => 70, 'position' => 0],
                ['title' => 'Kedisiplinan K3 dan komunikasi', 'category' => 'soft_skill', 'max_score' => 100, 'weight' => 30, 'position' => 1],
            ]);
        });
    }
}
