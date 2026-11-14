<?php

namespace Database\Seeders;

use App\Enums\CertificateStatus;
use App\Enums\InterviewStatus;
use App\Enums\LogbookStatus;
use App\Enums\UserRole;
use App\Models\Certificate;
use App\Models\InternshipAttendance;
use App\Models\InternshipLeaveRequest;
use App\Models\InternshipLogbook;
use App\Models\InternshipPlacement;
use App\Models\InternshipProject;
use App\Models\InterviewSession;
use App\Models\PartnerCompany;
use App\Models\PortfolioProject;
use App\Models\PracticumModule;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserRoleTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Akun Admin
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@vokasim.test'],
            [
                'name' => 'Administrator Sistem',
                'role' => UserRole::Admin,
                'identifier_number' => 'ADMIN-001',
                'study_program' => null,
                'institution_or_company' => 'Pusat Manajemen Vokasi',
                'phone' => '081234567890',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );

        // 2. Akun Dosen
        $dosen = User::query()->updateOrCreate(
            ['email' => 'dosen@vokasim.test'],
            [
                'name' => 'Dosen Demo',
                'role' => UserRole::Dosen,
                'identifier_number' => '198507152010121001',
                'study_program' => 'D4 Teknik Informatika',
                'institution_or_company' => 'Politeknik Negeri Vokasi',
                'phone' => '081234567892',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );

        // 3. Akun Mentor DUDI
        $mentor = User::query()->updateOrCreate(
            ['email' => 'mentor@vokasim.test'],
            [
                'name' => 'Mentor DUDI Demo',
                'role' => UserRole::MentorDudi,
                'identifier_number' => 'Senior Lead Software Engineer',
                'study_program' => null,
                'institution_or_company' => 'PT Solusi Teknologi Nusantara',
                'phone' => '081234567893',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );

        // 4. Akun Mahasiswa
        $mahasiswa = User::query()->updateOrCreate(
            ['email' => 'mahasiswa@vokasim.test'],
            [
                'name' => 'Mahasiswa Demo',
                'role' => UserRole::Mahasiswa,
                'identifier_number' => '210441100001',
                'study_program' => 'D4 Teknik Informatika',
                'institution_or_company' => 'Politeknik Negeri Vokasi',
                'phone' => '081234567891',
                'gpa' => 3.85,
                'semester' => 6,
                'graduation_year' => null,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );

        // 5. Data Modul Praktikum (Dosen sebagai pengampu, Mahasiswa terdaftar)
        $module = PracticumModule::query()->updateOrCreate(
            ['code' => 'TI-PRAK-DEMO'],
            [
                'lecturer_id' => $dosen->id,
                'title' => 'Pengembangan Web Enterprise & API',
                'study_program' => 'D4 Teknik Informatika',
                'semester' => 6,
                'skill_area' => 'Fullstack Web & API',
                'description' => 'Kelas praktikum intensif pengembangan web enterprise, microservices, dan integrasi sistem industri.',
                'timezone' => 'Asia/Jakarta',
                'starts_on' => now()->subMonth()->toDateString(),
                'ends_on' => now()->addMonths(3)->toDateString(),
                'published' => true,
            ]
        );

        $module->enrollments()->firstOrCreate(
            ['student_id' => $mahasiswa->id],
            ['status' => 'active', 'enrolled_at' => now('UTC')->subMonth()]
        );

        $sessionPast = $module->sessions()->firstOrCreate(
            ['title' => 'Arsitektur REST API & Standar Keamanan'],
            [
                'room' => 'Lab Komputer 3',
                'starts_at' => now('UTC')->subDays(2)->setTime(2, 0),
                'ends_at' => now('UTC')->subDays(2)->setTime(4, 0),
                'is_exam' => false,
                'cancelled' => false,
            ]
        );
        $sessionPast->attendances()->firstOrCreate(
            ['student_id' => $mahasiswa->id],
            ['recorded_by' => $dosen->id, 'status' => 'present', 'recorded_at' => $sessionPast->starts_at]
        );

        $module->sessions()->firstOrCreate(
            ['title' => 'Implementasi CI/CD & Deploy Cloud'],
            [
                'room' => 'Lab Cloud & Server',
                'starts_at' => now('UTC')->addDays(2)->setTime(2, 0),
                'ends_at' => now('UTC')->addDays(2)->setTime(4, 0),
                'is_exam' => false,
                'cancelled' => false,
            ]
        );

        $assignment = $module->assignments()->firstOrCreate(
            ['title' => 'Laporan Praktik Pembuatan RESTful API'],
            [
                'instructions' => 'Kirimkan dokumentasi endpoint, skema database, dan hasil unit testing layanan.',
                'due_at' => now('UTC')->addDays(5),
                'weight' => 1,
                'allow_late' => false,
            ]
        );

        if (! $assignment->rubricCriteria()->exists()) {
            $assignment->rubricCriteria()->createMany([
                ['title' => 'Kualitas Arsitektur & Clean Code', 'category' => 'hard_skill', 'max_score' => 100, 'weight' => 70, 'position' => 0],
                ['title' => 'Kelengkapan Dokumentasi & K3 Digital', 'category' => 'soft_skill', 'max_score' => 100, 'weight' => 30, 'position' => 1],
            ]);
        }

        if (! $assignment->submissions()->where('student_id', $mahasiswa->id)->exists()) {
            $path = 'practicum-submissions/'.$assignment->id.'/'.$mahasiswa->id.'/laporan-api.txt';
            Storage::disk('local')->put($path, 'Laporan Tugas: Implementasi RESTful API terstandarisasi industri lengkap dengan Unit Test.');
            $assignment->submissions()->create([
                'student_id' => $mahasiswa->id,
                'attempt' => 1,
                'status' => 'submitted',
                'notes' => 'Tugas sudah selesai dikerjakan sesuai spesifikasi instruktur.',
                'file_path' => $path,
                'original_name' => 'laporan-api.txt',
                'mime_type' => 'text/plain',
                'submitted_at' => now('UTC')->subHours(4),
                'is_late' => false,
            ]);
        }

        // 6. Penempatan Magang (Mahasiswa bimbingan Dosen & dimentori Mentor DUDI)
        $company = PartnerCompany::query()->firstOrCreate(
            ['name' => 'PT Solusi Teknologi Nusantara'],
            [
                'sector' => 'Teknologi Informasi & Perangkat Lunak',
                'address' => 'Jl. Industri Digital No. 88',
                'city' => 'Jakarta Selatan',
                'province' => 'DKI Jakarta',
                'latitude' => -6.2088,
                'longitude' => 106.8456,
                'contact_name' => 'Budi Santoso',
                'contact_email' => 'hr@solusiteknologi.test',
                'contact_phone' => '0215551234',
                'internship_quota' => 10,
                'is_active' => true,
            ]
        );

        $placement = InternshipPlacement::query()->updateOrCreate(
            [
                'student_id' => $mahasiswa->id,
                'mentor_id' => $mentor->id,
                'lecturer_id' => $dosen->id,
            ],
            [
                'partner_company_id' => $company->id,
                'company_name' => $company->name,
                'address' => $company->address,
                'latitude' => -6.2088,
                'longitude' => 106.8456,
                'radius_meters' => 200,
                'timezone' => 'Asia/Jakarta',
                'starts_on' => now()->subMonth()->toDateString(),
                'ends_on' => now()->addMonths(3)->toDateString(),
                'target_hours' => 600,
            ]
        );

        // Presensi hari ini untuk Mahasiswa
        if (! InternshipAttendance::query()
            ->where('internship_placement_id', $placement->id)
            ->whereDate('work_date', now()->toDateString())
            ->exists()) {
            InternshipAttendance::query()->create([
                'internship_placement_id' => $placement->id,
                'work_date' => now()->toDateString(),
                'checked_in_at' => now()->setTime(8, 0),
                'checked_out_at' => now()->setTime(17, 0),
                'check_in_latitude' => -6.2088,
                'check_in_longitude' => 106.8456,
                'check_in_accuracy' => 10.0,
                'check_in_distance' => 15.0,
                'check_out_latitude' => -6.2088,
                'check_out_longitude' => 106.8456,
                'check_out_accuracy' => 10.0,
                'check_out_distance' => 12.0,
                'radius_meters' => 200,
            ]);
        }

        // Logbook 1: Terverifikasi
        if (! InternshipLogbook::query()
            ->where('internship_placement_id', $placement->id)
            ->whereDate('work_date', now()->subDays(2)->toDateString())
            ->exists()) {
            InternshipLogbook::query()->create([
                'internship_placement_id' => $placement->id,
                'work_date' => now()->subDays(2)->toDateString(),
                'description' => 'Mengembangkan modul autentikasi dan integrasi single sign-on (SSO) pada portal mitra.',
                'effective_minutes' => 480,
                'status' => LogbookStatus::Verified,
                'submitted_at' => now()->subDays(2)->setTime(17, 0),
                'mentor_approved_at' => now()->subDays(2)->setTime(18, 0),
                'verified_at' => now()->subDay()->setTime(10, 0),
            ]);
        }

        // Logbook 2: Disetujui Mentor -> Menunggu Validasi Dosen
        if (! InternshipLogbook::query()
            ->where('internship_placement_id', $placement->id)
            ->whereDate('work_date', now()->subDay()->toDateString())
            ->exists()) {
            InternshipLogbook::query()->create([
                'internship_placement_id' => $placement->id,
                'work_date' => now()->subDay()->toDateString(),
                'description' => 'Menyusun unit testing dan API documentation menggunakan OpenAPI/Swagger untuk modul order.',
                'effective_minutes' => 480,
                'status' => LogbookStatus::MentorApproved,
                'submitted_at' => now()->subDay()->setTime(17, 0),
                'mentor_approved_at' => now()->subDay()->setTime(18, 30),
            ]);
        }

        // Logbook 3: Diajukan Mahasiswa -> Menunggu Review Mentor DUDI
        if (! InternshipLogbook::query()
            ->where('internship_placement_id', $placement->id)
            ->whereDate('work_date', now()->toDateString())
            ->exists()) {
            InternshipLogbook::query()->create([
                'internship_placement_id' => $placement->id,
                'work_date' => now()->toDateString(),
                'description' => 'Refactoring database query untuk optimasi performa dashboard admin serta perbaikan bug relasi.',
                'effective_minutes' => 480,
                'status' => LogbookStatus::PendingMentor,
                'submitted_at' => now()->setTime(16, 45),
            ]);
        }

        // Pengajuan Izin Menunggu Review Mentor
        InternshipLeaveRequest::query()->firstOrCreate(
            [
                'internship_placement_id' => $placement->id,
                'starts_on' => now()->addDays(3)->toDateString(),
            ],
            [
                'kind' => 'permit',
                'ends_on' => now()->addDays(3)->toDateString(),
                'reason' => 'Izin menghadiri uji kompetensi sertifikasi keahlian BNSP.',
                'status' => 'pending',
            ]
        );

        // Proyek Magang
        InternshipProject::query()->firstOrCreate(
            [
                'internship_placement_id' => $placement->id,
                'title' => 'Pengembangan Sistem Monitoring Logistik Perusahaan',
            ],
            [
                'mentor_id' => $mentor->id,
                'description' => 'Membangun dashboard pelacakan distribusi logistik internal.',
                'starts_on' => now()->subMonth()->toDateString(),
                'due_on' => now()->addMonth()->toDateString(),
                'target_minutes' => 4800,
                'completed_minutes' => 2400,
            ]
        );

        // 7. Portofolio Mahasiswa
        if (! PortfolioProject::query()->where('student_id', $mahasiswa->id)->exists()) {
            PortfolioProject::query()->create([
                'student_id' => $mahasiswa->id,
                'title' => 'Sistem Informasi Manajemen Magang & Portofolio Vokasi',
                'description' => 'Aplikasi web enterprise untuk mengelola logbook magang, presensi berbasis geofencing, integrasi modul praktikum, dan rekam jejak sertifikasi.',
                'skills' => ['Laravel', 'Livewire', 'Tailwind CSS', 'PostgreSQL', 'Docker'],
                'project_url' => 'https://github.com/vokasi/vokasim',
                'completed_on' => now()->subMonth()->toDateString(),
                'is_published' => true,
            ]);
        }

        // 8. Sertifikat Mahasiswa
        if (! Certificate::query()->where('student_id', $mahasiswa->id)->exists()) {
            $cert1Path = 'certificates/demo/'.Str::uuid().'.encrypted';
            Certificate::query()->create([
                'student_id' => $mahasiswa->id,
                'holder_name' => $mahasiswa->name,
                'title' => 'Sertifikasi Kompetensi Pemrograman Web Enterprise (BNSP)',
                'issuer' => 'LSP Informatika Nasional',
                'credential_number' => 'KOMP-100001',
                'skills' => ['php', 'laravel', 'sql', 'rest-api'],
                'issued_on' => now()->subMonths(2)->toDateString(),
                'expires_on' => now()->addYears(3)->toDateString(),
                'status' => CertificateStatus::Verified,
                'verified_at' => now()->subMonth(),
                'verified_by' => $dosen->id,
                'file_path' => $cert1Path,
                'file_name' => 'sertifikat-bnsp.pdf',
                'file_mime' => 'application/pdf',
                'file_size' => 32,
                'public_consent_at' => now(),
            ]);
            Storage::disk('local')->put($cert1Path, Crypt::encryptString('%PDF-1.4 Sertifikat pengujian'));

            $cert2Path = 'certificates/demo/'.Str::uuid().'.encrypted';
            Certificate::query()->create([
                'student_id' => $mahasiswa->id,
                'holder_name' => $mahasiswa->name,
                'title' => 'Cloud Computing & Containerization Practitioner',
                'issuer' => 'AWS Academy / Cloud Foundation',
                'credential_number' => 'KOMP-100002',
                'skills' => ['docker', 'aws', 'linux'],
                'issued_on' => now()->subMonth()->toDateString(),
                'expires_on' => now()->addYears(2)->toDateString(),
                'status' => CertificateStatus::Pending,
                'file_path' => $cert2Path,
                'file_name' => 'sertifikat-cloud.pdf',
                'file_mime' => 'application/pdf',
                'file_size' => 32,
                'public_consent_at' => now(),
            ]);
            Storage::disk('local')->put($cert2Path, Crypt::encryptString('%PDF-1.4 Sertifikat pengujian'));
        }

        // 9. Simulasi Wawancara Mahasiswa
        if (! InterviewSession::query()->where('student_id', $mahasiswa->id)->exists()) {
            InterviewSession::query()->create([
                'public_id' => (string) Str::uuid(),
                'student_id' => $mahasiswa->id,
                'target_position' => 'Fullstack Web Engineer',
                'difficulty' => 'intermediate',
                'interview_type' => 'technical',
                'interaction_mode' => 'text',
                'status' => InterviewStatus::Completed,
                'question_count' => 5,
                'answer_seconds' => 180,
                'use_context' => false,
                'context_snapshot' => [],
                'consented_at' => now(),
                'started_at' => now()->subMinutes(20),
                'completed_at' => now(),
                'ai_model' => 'gpt-6-luna',
                'retry_count' => 0,
                'overall_score' => 86.5,
                'star_score' => 88.0,
                'technical_score' => 89.0,
                'communication_score' => 82.5,
                'evaluation' => [
                    'summary' => 'Kandidat memiliki pemahaman mendalam tentang arsitektur web modern, basis data relasional, dan clean code principles. Respon menggunakan metode STAR terstruktur baik.',
                    'strengths' => [
                        'Penguasaan framework backend dan arsitektur database sangat solid.',
                        'Penjelasan problem solving sistematis dan terukur.',
                        'Familiar dengan konsep deployment dan CI/CD pipeline.',
                    ],
                    'improvements' => [
                        'Eksplorasi lebih mendalam pada strategi caching terdistribusi (Redis).',
                        'Pertajam metrik peningkatan performa pada penjelasan hasil proyek.',
                    ],
                ],
            ]);
        }
    }
}
