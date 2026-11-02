<?php

namespace Database\Factories;

use App\Enums\CertificateStatus;
use App\Models\Certificate;
use App\Models\CertificateReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CertificateReview>
 */
class CertificateReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'certificate_id' => Certificate::factory(),
            'reviewer_id' => User::factory()->dosen(),
            'from_status' => CertificateStatus::Pending,
            'to_status' => CertificateStatus::Rejected,
            'feedback' => 'Nama kompetensi perlu disesuaikan dengan dokumen penerbit.',
        ];
    }
}
