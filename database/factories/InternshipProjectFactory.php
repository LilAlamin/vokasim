<?php

namespace Database\Factories;

use App\Models\InternshipPlacement;
use App\Models\InternshipProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InternshipProject> */
class InternshipProjectFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'internship_placement_id' => InternshipPlacement::factory(),
            'mentor_id' => fn (array $attributes): int => InternshipPlacement::query()->whereKey($attributes['internship_placement_id'])->firstOrFail()->mentor_id,
            'title' => 'Implementasi '.fake()->word(),
            'description' => 'Melaksanakan proyek lapangan dan menyusun dokumentasi hasil pekerjaan.',
            'starts_on' => today()->toDateString(),
            'due_on' => today()->addWeek()->toDateString(),
            'target_minutes' => 2400,
            'completed_minutes' => 600,
            'feedback' => 'Capaian awal sudah ditinjau mentor.',
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => ['completed_minutes' => $attributes['target_minutes']]);
    }
}
