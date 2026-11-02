<?php

namespace Database\Factories;

use App\Models\InternshipPlacement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InternshipPlacement> */
class InternshipPlacementFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'student_id' => User::factory(), 'mentor_id' => User::factory()->mentorDudi(), 'lecturer_id' => User::factory()->dosen(),
            'company_name' => fake()->company(), 'address' => fake()->address(),
            'latitude' => -6.2000000, 'longitude' => 106.8166667, 'radius_meters' => 100, 'timezone' => 'Asia/Jakarta',
            'starts_on' => now()->subMonth()->toDateString(), 'ends_on' => now()->addMonths(3)->toDateString(), 'target_hours' => 600,
        ];
    }
}
