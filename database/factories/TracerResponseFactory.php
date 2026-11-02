<?php

namespace Database\Factories;

use App\Models\TracerResponse;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TracerResponse> */
class TracerResponseFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'graduation_year' => today()->subYear()->year,
            'graduated_on' => today()->subYear()->startOfYear()->toDateString(),
            'study_program' => fn (array $attributes): ?string => User::query()->whereKey($attributes['user_id'])->firstOrFail()->study_program,
            'employment_status' => 'employed',
            'employer_name' => fake()->company(),
            'job_title' => 'Teknisi',
            'first_job_started_on' => today()->subYear()->startOfYear()->addMonths(3)->toDateString(),
            'wait_months' => 3,
            'first_monthly_salary' => 5000000,
            'field_alignment' => 'aligned',
            'skill_gaps' => ['komunikasi', 'bahasa inggris'],
            'consented_at' => now(),
        ];
    }

    public function seeking(): static
    {
        return $this->state(fn (): array => [
            'employment_status' => 'seeking',
            'employer_name' => null,
            'job_title' => null,
            'first_job_started_on' => null,
            'wait_months' => null,
            'first_monthly_salary' => null,
            'field_alignment' => null,
        ]);
    }
}
