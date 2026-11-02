<?php

namespace Database\Factories;

use App\Models\InternshipLeaveRequest;
use App\Models\InternshipPlacement;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InternshipLeaveRequest> */
class InternshipLeaveRequestFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'internship_placement_id' => InternshipPlacement::factory(),
            'kind' => 'leave',
            'starts_on' => today()->toDateString(),
            'ends_on' => today()->toDateString(),
            'reason' => 'Mengikuti kegiatan akademik yang telah dijadwalkan.',
            'evidence_path' => null,
            'status' => 'pending',
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => 'approved',
            'reviewer_id' => fn (array $attributes): int => InternshipPlacement::query()->whereKey($attributes['internship_placement_id'])->firstOrFail()->mentor_id,
            'feedback' => 'Izin telah dikonfirmasi.',
            'reviewed_at' => now(),
        ]);
    }
}
