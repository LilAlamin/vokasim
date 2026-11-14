<?php

namespace Database\Factories;

use App\Enums\InterviewStatus;
use App\Models\InterviewSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<InterviewSession>
 */
class InterviewSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_id' => (string) Str::uuid(),
            'public_id' => (string) Str::uuid(),
            'student_id' => User::factory(), 'target_position' => 'Network Technician',
            'difficulty' => 'beginner', 'interview_type' => 'behavioral', 'interaction_mode' => 'text',
            'status' => InterviewStatus::Preparing, 'question_count' => 5, 'answer_seconds' => 180,
            'use_context' => false, 'context_snapshot' => [], 'consented_at' => now(),
            'ai_model' => 'gpt-6-luna', 'retry_count' => 0,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => ['status' => InterviewStatus::Active]);
    }

    public function evaluating(): static
    {
        return $this->state(fn (): array => ['status' => InterviewStatus::Evaluating, 'started_at' => now()->subMinutes(15)]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => ['status' => InterviewStatus::Completed, 'started_at' => now()->subMinutes(20), 'completed_at' => now(),
            'overall_score' => 80, 'star_score' => 80, 'technical_score' => 80, 'communication_score' => 80,
            'evaluation' => ['summary' => 'Jawaban latihan sudah terstruktur.', 'strengths' => ['Menjelaskan tindakan dengan jelas.'], 'improvements' => ['Lengkapi hasil yang terukur.']],
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => ['status' => InterviewStatus::Failed, 'failure_stage' => 'generation', 'error_message' => 'Pertanyaan belum berhasil dibuat.']);
    }
}
