<?php

namespace Database\Factories;

use App\Models\InternshipAttendance;
use App\Models\InternshipPlacement;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InternshipAttendance> */
class InternshipAttendanceFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $start = CarbonImmutable::today('Asia/Jakarta')->setHour(8)->utc();

        return [
            'internship_placement_id' => InternshipPlacement::factory(), 'work_date' => $start->setTimezone('Asia/Jakarta')->toDateString(),
            'checked_in_at' => $start, 'checked_out_at' => null,
            'check_in_latitude' => -6.2, 'check_in_longitude' => 106.8166667, 'check_in_accuracy' => 5, 'check_in_distance' => 0, 'radius_meters' => 100,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'checked_out_at' => CarbonImmutable::parse($attributes['checked_in_at'])->addHours(8),
            'check_out_latitude' => -6.2, 'check_out_longitude' => 106.8166667, 'check_out_accuracy' => 5, 'check_out_distance' => 0,
        ]);
    }
}
