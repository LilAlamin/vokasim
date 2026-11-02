<?php

namespace Database\Factories;

use App\Models\InterviewQuestion;
use App\Models\InterviewSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InterviewQuestion>
 */
class InterviewQuestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'interview_session_id' => InterviewSession::factory()->active(), 'sequence' => 1,
            'category' => 'behavioral', 'prompt' => 'Ceritakan pengalaman Anda menyelesaikan gangguan jaringan saat magang.',
        ];
    }

    public function opened(): static
    {
        return $this->state(fn (): array => ['opened_at' => now()]);
    }

    public function answered(): static
    {
        return $this->state(fn (): array => ['opened_at' => now()->subMinutes(2), 'answered_at' => now(), 'answer_text' => 'Saat jaringan laboratorium terputus, saya memeriksa kabel dan konfigurasi VLAN. Setelah VLAN diperbaiki, seluruh komputer kembali terhubung.', 'timed_out' => false]);
    }
}
