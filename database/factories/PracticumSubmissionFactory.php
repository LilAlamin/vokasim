<?php

namespace Database\Factories;

use App\Models\PracticumAssignment;
use App\Models\PracticumSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PracticumSubmission> */
class PracticumSubmissionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'practicum_assignment_id' => PracticumAssignment::factory(), 'student_id' => User::factory(),
            'attempt' => 1, 'status' => 'submitted', 'notes' => 'Laporan hasil praktik dan evaluasi keselamatan.',
            'file_path' => 'practicum-submissions/'.fake()->uuid().'.pdf', 'original_name' => 'laporan.pdf', 'mime_type' => 'application/pdf',
            'submitted_at' => now('UTC'), 'is_late' => false,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (PracticumSubmission $submission): void {
            $submission->assignment->module->enrollments()->firstOrCreate(['student_id' => $submission->student_id], ['status' => 'active', 'enrolled_at' => now('UTC')->subWeek()]);
        });
    }

    public function revisionRequested(): static
    {
        return $this->state(fn (): array => ['status' => 'revision_requested', 'revision_feedback' => 'Lengkapi analisis pengukuran dan kesimpulan.', 'reviewed_at' => now('UTC')]);
    }

    public function graded(): static
    {
        return $this->state(fn (): array => ['status' => 'graded']);
    }
}
