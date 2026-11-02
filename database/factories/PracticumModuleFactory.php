<?php

namespace Database\Factories;

use App\Models\PracticumModule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PracticumModule> */
class PracticumModuleFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'lecturer_id' => User::factory()->dosen(), 'code' => fake()->unique()->bothify('PRAK-####-??'),
            'title' => 'Praktikum '.fake()->sentence(3), 'study_program' => 'Teknik Mesin', 'semester' => 3,
            'skill_area' => 'Manufaktur', 'description' => fake()->paragraph(), 'timezone' => 'Asia/Jakarta',
            'starts_on' => now()->subMonth()->toDateString(), 'ends_on' => now()->addMonths(4)->toDateString(), 'published' => true,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['published' => false]);
    }
}
