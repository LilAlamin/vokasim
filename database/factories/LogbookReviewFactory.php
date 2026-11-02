<?php

namespace Database\Factories;

use App\Enums\LogbookStatus;
use App\Models\InternshipLogbook;
use App\Models\LogbookReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LogbookReview> */
class LogbookReviewFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'internship_logbook_id' => InternshipLogbook::factory()->pending(),
            'reviewer_id' => fn (array $attributes): int => InternshipLogbook::query()->where('id', $attributes['internship_logbook_id'])->firstOrFail()->placement->student_id,
            'from_status' => LogbookStatus::Draft, 'to_status' => LogbookStatus::PendingMentor, 'feedback' => null,
        ];
    }
}
