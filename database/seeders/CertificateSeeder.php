<?php

namespace Database\Seeders;

use App\Enums\CertificateStatus;
use App\Enums\UserRole;
use App\Models\Certificate;
use App\Models\User;
use Illuminate\Database\Seeder;

class CertificateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $student = User::query()->where('role', UserRole::Mahasiswa)->orderBy('id')->first() ?? User::factory()->create();

        if (! Certificate::query()->where('student_id', $student->id)->where('title', 'Kompetensi Pemrograman Web (Data Contoh)')->exists()) {
            $certificate = Certificate::factory()->for($student, 'student')->withFile()->create([
                'holder_name' => $student->name,
                'title' => 'Kompetensi Pemrograman Web (Data Contoh)',
                'issuer' => 'Lembaga Demo — Bukan Sertifikat Resmi',
                'skills' => ['php', 'sql'],
            ]);
            $certificate->reviews()->create(['reviewer_id' => $student->id, 'to_status' => CertificateStatus::Pending, 'feedback' => 'Data contoh untuk mempelajari alur. Ganti dengan dokumen asli sebelum meminta verifikasi.']);
        }
    }
}
