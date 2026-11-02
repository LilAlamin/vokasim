<?php

namespace Database\Factories;

use App\Models\InterviewNote;
use App\Models\InterviewSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InterviewNote>
 */
class InterviewNoteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'interview_session_id' => InterviewSession::factory()->completed(),
            'reviewer_id' => User::factory()->dosen(), 'body' => 'Tambahkan hasil pengukuran sesudah tindakan perbaikan untuk memperkuat jawaban STAR.',
        ];
    }
}
