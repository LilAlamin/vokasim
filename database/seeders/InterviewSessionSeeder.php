<?php

namespace Database\Seeders;

use App\Enums\InterviewStatus;
use App\Enums\UserRole;
use App\Models\InterviewNote;
use App\Models\InterviewQuestion;
use App\Models\InterviewSession;
use App\Models\User;
use Illuminate\Database\Seeder;

class InterviewSessionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $student = User::query()->where('role', UserRole::Mahasiswa)->orderBy('id')->first();
        if (! $student) {
            return;
        }

        if (! InterviewSession::query()->where('student_id', $student->id)->where('status', InterviewStatus::Completed)->exists()) {
            $session = InterviewSession::factory()->for($student, 'student')->completed()->create([
                'target_position' => 'Automation Maintenance Specialist',
                'difficulty' => 'intermediate',
                'interview_type' => 'technical',
                'interaction_mode' => 'text',
                'question_count' => 5,
                'overall_score' => 84.5,
                'star_score' => 86.0,
                'technical_score' => 88.0,
                'communication_score' => 80.0,
                'evaluation' => [
                    'summary' => 'Kandidat menunjukkan penguasaan yang solid terhadap troubleshooting PLC, sistem hidrolik, dan prosedur keselamatan kerja K3 (LOTO). Struktur jawaban berbasis metode STAR sangat runtut dan mudah dipahami.',
                    'strengths' => [
                        'Penerapan metode STAR runtut (Situation, Task, Action, Result).',
                        'Penguasaan istilah teknis vokasi (LOTO, kalibrasi sensor, ladder diagram) sangat baik.',
                        'Solusi mitigasi insiden mesin mempertimbangkan aspek keselamatan kerja.',
                    ],
                    'improvements' => [
                        'Sertakan metrik kuantitatif hasil perbaikan (misal: persentase penurunan downtime).',
                        'Kurangi jeda ragu saat menjelaskan diagnosa gangguan kelistrikan.',
                    ],
                ],
            ]);

            $sampleQuestions = [
                [
                    'sequence' => 1,
                    'category' => 'introduction',
                    'prompt' => 'Ceritakan pengalaman praktik terbaik Anda di bengkel/industri dan mengapa Anda tertarik dengan posisi Automation Maintenance Specialist ini?',
                    'answer_text' => 'Saya adalah mahasiswa vokasi teknologi otomasi yang telah menyelesaikan 420 jam magang di PT Astra Honda Motor. Pengalaman terbaik saya adalah melakukan kalibrasi sensor proximity dan pemeliharaan mesin pres hidrolik, di mana saya berhasil mendeteksi anomali tekanan sebelum menyebabkan kegagalan komponen.',
                    'evaluation' => [
                        'star_score' => 88,
                        'technical_score' => 85,
                        'communication_score' => 82,
                        'feedback' => 'Pengenalan diri ringkas, relevan, dan langsung menyoroti pengalaman magang industri nyata.',
                        'improved_answer' => 'Contoh optimal: Tambahkan motivasi jangka panjang terkait kontribusi terhadap efisiensi lini manufaktur.',
                        'word_count' => 52,
                        'filler_count' => 0,
                    ],
                ],
                [
                    'sequence' => 2,
                    'category' => 'behavioral',
                    'prompt' => 'Ceritakan situasi saat mesin di lini produksi mengalami downtime mendadak. Tindakan apa yang Anda ambil pertama kali sesuai SOP?',
                    'answer_text' => 'Situation: Pada shift pagi, konveyor Line 2 berhenti mendadak karena alarm sensor beban. Task: Sebagai teknisi magang, saya ditugaskan membantu penyelia menemukan akar masalah. Action: Sesuai SOP K3, saya memasang tag Lockout/Tagout (LOTO) terlebih dahulu, lalu memeriksa sinyal input pada PLC dan mendapati relay daya aus. Result: Kami mengganti modul relay dalam 25 menit dan konveyor beroperasi normal tanpa insiden K3.',
                    'evaluation' => [
                        'star_score' => 90,
                        'technical_score' => 92,
                        'communication_score' => 85,
                        'feedback' => 'Format STAR sangat jelas dan memprioritaskan prosedur keselamatan LOTO sebelum intervensi mekanik.',
                        'improved_answer' => 'Contoh optimal: Sangat baik. Bisa diperkuat dengan menyebutkan pencatatan insiden di buku log pemeliharaan preventif.',
                        'word_count' => 68,
                        'filler_count' => 0,
                    ],
                ],
                [
                    'sequence' => 3,
                    'category' => 'technical',
                    'prompt' => 'Bagaimana langkah-langkah Anda dalam melakukan kalibrasi dan pengujian toleransi pada modul kontrol pneumatik?',
                    'answer_text' => 'Pertama, saya memverifikasi tekanan udara utama dari regulator pada rentang standar 5-6 bar. Selanjutnya, saya memeriksa kondisi seal dan katup solenoid menggunakan multimeter untuk memastikan suplai tegangan 24V DC stabil. Terakhir, saya menjalankan uji siklus silinder tanpa beban dan mengukur waktu respons aktuator menggunakan sensor limit switch.',
                    'evaluation' => [
                        'star_score' => 84,
                        'technical_score' => 88,
                        'communication_score' => 80,
                        'feedback' => 'Urutan teknis sangat runtut mulai dari pasokan tekanan hingga verifikasi sinyal kontrol.',
                        'improved_answer' => 'Contoh optimal: Sertakan jenis alat ukur toleransi yang digunakan (misal: pressure gauge terkalibrasi).',
                        'word_count' => 58,
                        'filler_count' => 0,
                    ],
                ],
                [
                    'sequence' => 4,
                    'category' => 'scenario',
                    'prompt' => 'Jika Anda menemukan perbedaan data antara sensor visual dan limit switch fisik pada lengan robot, mana yang Anda prioritaskan dan bagaimana verifikasinya?',
                    'answer_text' => 'Saya memprioritaskan aspek keselamatan terlebih dahulu dengan menekan tombol pause operasi robot. Selanjutnya saya memeriksa apakah ada kotoran debu atau pantulan cahaya yang mengganggu optik sensor visual. Jika sensor visual normal, saya menguji kontinuitas sakelar batas mekanik. Saya memverifikasi keduanya melalui status bit memory di ladder diagram PLC.',
                    'evaluation' => [
                        'star_score' => 85,
                        'technical_score' => 86,
                        'communication_score' => 82,
                        'feedback' => 'Pendekatan analitis yang mengutamakan keselamatan dan verifikasi logika PLC.',
                        'improved_answer' => 'Contoh optimal: Jelaskan toleransi kesalahan yang diizinkan sebelum sistem wajib masuk mode manual override.',
                        'word_count' => 61,
                        'filler_count' => 0,
                    ],
                ],
                [
                    'sequence' => 5,
                    'category' => 'scenario',
                    'prompt' => 'Bagaimana Anda memastikan hasil pekerjaan perbaikan Anda terdokumentasi dengan baik agar rekan kerja di shift berikutnya memahami status mesin?',
                    'answer_text' => 'Saya mendokumentasikan setiap suku cadang yang diganti, nilai pengukuran sebelum dan sesudah kalibrasi, serta sisa catatan pengamatan ke dalam E-Logbook VokaSim. Saat serah terima shift (handover), saya menjelaskan ringkasan tindakan secara lisan dan meminta tanda tangan penyelia industri.',
                    'evaluation' => [
                        'star_score' => 82,
                        'technical_score' => 80,
                        'communication_score' => 84,
                        'feedback' => 'Komunikasi antarteknisi dan integrasi E-Logbook ditunjukkan dengan sangat baik.',
                        'improved_answer' => 'Contoh optimal: Sebutkan juga pelepasan label LOTO saat serah terima agar mesin siap dinyalakan.',
                        'word_count' => 53,
                        'filler_count' => 0,
                    ],
                ],
            ];

            foreach ($sampleQuestions as $q) {
                InterviewQuestion::create([
                    'interview_session_id' => $session->id,
                    'sequence' => $q['sequence'],
                    'category' => $q['category'],
                    'prompt' => $q['prompt'],
                    'answer_text' => $q['answer_text'],
                    'opened_at' => now()->subMinutes(25 - $q['sequence'] * 4),
                    'answered_at' => now()->subMinutes(23 - $q['sequence'] * 4),
                    'timed_out' => false,
                    'evaluation' => $q['evaluation'],
                ]);
            }

            $lecturer = User::query()->where('role', UserRole::Dosen)->first();
            if ($lecturer) {
                InterviewNote::create([
                    'interview_session_id' => $session->id,
                    'reviewer_id' => $lecturer->id,
                    'interview_question_id' => null,
                    'body' => 'Latihan yang sangat impresif. Penjelasan prosedur Lockout/Tagout (LOTO) dan pemeliharaan pneumatik menunjukkan kesiapan tinggi untuk terjun ke industri manufaktur. Pertahankan konsistensi metode STAR.',
                ]);
            }
        }
    }
}
