<?php

namespace Database\Factories;

use App\Models\PracticumEnrollment;
use App\Models\PracticumModule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PracticumEnrollment> */
class PracticumEnrollmentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['practicum_module_id' => PracticumModule::factory(), 'student_id' => User::factory(), 'status' => 'active', 'enrolled_at' => now('UTC')->subDays(14)];
    }

    public function withdrawn(): static
    {
        return $this->state(fn (): array => ['status' => 'withdrawn']);
    }
}
