<?php

namespace Database\Factories;

use App\Enums\LogbookStatus;
use App\Models\InternshipLogbook;
use App\Models\InternshipPlacement;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InternshipLogbook> */
class InternshipLogbookFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'internship_placement_id' => InternshipPlacement::factory(), 'work_date' => now('Asia/Jakarta')->toDateString(),
            'description' => 'Melakukan inspeksi panel kontrol dan pengujian sensor mesin produksi.',
            'effective_minutes' => 420, 'photo_path' => null, 'status' => LogbookStatus::Draft,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => ['status' => LogbookStatus::PendingMentor, 'submitted_at' => now()]);
    }

    public function mentorApproved(): static
    {
        return $this->pending()->state(fn (): array => ['status' => LogbookStatus::MentorApproved, 'mentor_approved_at' => now()]);
    }

    public function verified(): static
    {
        return $this->mentorApproved()->state(fn (): array => ['status' => LogbookStatus::Verified, 'verified_at' => now()]);
    }
}
