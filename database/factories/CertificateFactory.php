<?php

namespace Database\Factories;

use App\Enums\CertificateStatus;
use App\Models\Certificate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @extends Factory<Certificate>
 */
class CertificateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => User::factory(),
            'holder_name' => fake()->name(),
            'title' => 'Sertifikat '.fake()->sentence(3),
            'issuer' => 'Lembaga Sertifikasi Contoh',
            'credential_number' => 'KOMP-'.fake()->unique()->numerify('######'),
            'skills' => ['php', 'sql'],
            'issued_on' => now()->subMonth()->toDateString(),
            'expires_on' => now()->addYear()->toDateString(),
            'status' => CertificateStatus::Pending,
            'file_path' => 'certificates/demo/'.Str::uuid().'.encrypted',
            'file_name' => 'sertifikat.pdf',
            'file_mime' => 'application/pdf',
            'file_size' => 32,
            'public_consent_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => ['status' => CertificateStatus::Pending]);
    }

    public function verified(): static
    {
        return $this->state(fn (): array => ['status' => CertificateStatus::Verified, 'verified_by' => User::factory()->dosen(), 'verified_at' => now()]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => ['status' => CertificateStatus::Rejected]);
    }

    public function revoked(): static
    {
        return $this->verified()->state(fn (): array => ['status' => CertificateStatus::Revoked, 'revoked_by' => User::factory()->admin(), 'revoked_at' => now()]);
    }

    public function expired(): static
    {
        return $this->verified()->state(fn (): array => ['issued_on' => now()->subYears(2)->toDateString(), 'expires_on' => now()->subDay()->toDateString()]);
    }

    public function withFile(): static
    {
        return $this->afterCreating(function (Certificate $certificate): void {
            Storage::disk('local')->put($certificate->file_path, Crypt::encryptString('%PDF-1.4 Sertifikat pengujian'));
        });
    }
}
