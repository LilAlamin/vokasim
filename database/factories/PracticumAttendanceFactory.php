<?php

namespace Database\Factories;

use App\Models\PracticumAttendance;
use App\Models\PracticumSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PracticumAttendance> */
class PracticumAttendanceFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'practicum_session_id' => PracticumSession::factory(), 'student_id' => User::factory(),
            'recorded_by' => fn (array $attributes): int => $attributes['student_id'],
            'status' => 'present', 'note' => null, 'recorded_at' => now('UTC'),
        ];
    }
}
