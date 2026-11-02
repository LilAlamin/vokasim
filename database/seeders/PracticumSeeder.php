<?php

namespace Database\Seeders;

use App\Actions\AssessPracticumSubmission;
use App\Enums\UserRole;
use App\Models\PracticumModule;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class PracticumSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $lecturer = User::query()->where('role', UserRole::Dosen)->first()
            ?? User::factory()->dosen()->create(['name' => 'Dosen Praktikum Demo', 'email' => 'dosen.praktikum@example.test']);
        $student = User::query()->where('role', UserRole::Mahasiswa)->first()
            ?? User::factory()->create(['name' => 'Mahasiswa Praktikum Demo', 'email' => 'mahasiswa.praktikum@example.test', 'study_program' => 'Teknik Mesin']);
        $module = PracticumModule::query()->firstOrCreate(['code' => 'PRAKTIKUM-DEMO'], [
            'lecturer_id' => $lecturer->id, 'title' => 'Manufaktur Presisi (Data Contoh)',
            'study_program' => $student->study_program ?: 'Teknik Mesin', 'semester' => 3, 'skill_area' => 'Manufaktur dan K3',
            'description' => 'Kelas contoh untuk mempelajari persiapan alat, pengukuran komponen, dokumentasi hasil, dan keselamatan kerja.',
            'timezone' => 'Asia/Jakarta', 'starts_on' => now()->subMonth()->toDateString(), 'ends_on' => now()->addMonths(3)->toDateString(), 'published' => true,
        ]);
        $module->enrollments()->firstOrCreate(['student_id' => $student->id], ['status' => 'active', 'enrolled_at' => now('UTC')->subWeek()]);

        foreach (['k3' => ['Panduan K3 (Contoh)', "Panduan pembelajaran contoh.\nGunakan APD yang sesuai, periksa area kerja, dan minta pengarahan instruktur sebelum menggunakan mesin. Ikuti SOP resmi laboratorium Anda."], 'sop' => ['SOP pengukuran (Contoh)', "Periksa kondisi alat ukur.\nLakukan kalibrasi sesuai instruksi.\nCatat hasil pengukuran dan toleransinya.\nSimpan alat dan bersihkan area kerja."]] as $kind => [$title, $content]) {
            if ($module->materials()->where('kind', $kind)->exists()) {
                continue;
            }

            $path = 'practicum-materials/'.$module->id.'/demo-'.$kind.'.txt';
            Storage::disk('local')->put($path, $content);
            $module->materials()->create(['uploaded_by' => $lecturer->id, 'title' => $title, 'kind' => $kind, 'file_path' => $path, 'original_name' => $kind.'-contoh.txt', 'mime_type' => 'text/plain', 'size' => strlen($content)]);
        }

        $session = $module->sessions()->firstOrCreate(['title' => 'Orientasi alat dan keselamatan'], ['room' => 'Bengkel A', 'starts_at' => now('UTC')->subDay()->setTime(1, 0), 'ends_at' => now('UTC')->subDay()->setTime(3, 0), 'is_exam' => false, 'cancelled' => false]);
        $session->attendances()->firstOrCreate(['student_id' => $student->id], ['recorded_by' => $lecturer->id, 'status' => 'present', 'recorded_at' => $session->starts_at]);
        $module->sessions()->firstOrCreate(['title' => 'Praktik pengukuran komponen'], ['room' => 'Bengkel A', 'starts_at' => now('UTC')->addDay()->setTime(1, 0), 'ends_at' => now('UTC')->addDay()->setTime(3, 0), 'is_exam' => false, 'cancelled' => false]);
        $module->sessions()->firstOrCreate(['title' => 'Ujian praktik pengukuran'], ['room' => 'Bengkel A', 'starts_at' => now('UTC')->addWeek()->setTime(1, 0), 'ends_at' => now('UTC')->addWeek()->setTime(3, 0), 'is_exam' => true, 'cancelled' => false]);
        $assignment = $module->assignments()->firstOrCreate(['title' => 'Laporan pengukuran awal'], ['instructions' => 'Kirim dokumentasi prosedur, hasil pengukuran, dan analisis toleransi komponen.', 'due_at' => now('UTC')->addDays(3), 'weight' => 1, 'allow_late' => false]);

        if (! $assignment->rubricCriteria()->exists()) {
            $assignment->rubricCriteria()->createMany([
                ['title' => 'Ketepatan hasil pengukuran', 'category' => 'hard_skill', 'max_score' => 100, 'weight' => 70, 'position' => 0],
                ['title' => 'K3 dan komunikasi hasil', 'category' => 'soft_skill', 'max_score' => 100, 'weight' => 30, 'position' => 1],
            ]);
        }

        if (! $assignment->submissions()->exists()) {
            $path = 'practicum-submissions/'.$assignment->id.'/'.$student->id.'/laporan-contoh.txt';
            Storage::disk('local')->put($path, 'Laporan contoh: pengukuran komponen dan pencatatan toleransi mengikuti panduan instruktur.');
            $submission = $assignment->submissions()->create(['student_id' => $student->id, 'attempt' => 1, 'status' => 'submitted', 'notes' => 'Laporan ini merupakan data contoh.', 'file_path' => $path, 'original_name' => 'laporan-contoh.txt', 'mime_type' => 'text/plain', 'submitted_at' => now('UTC'), 'is_late' => false]);
            (new AssessPracticumSubmission)->handle($lecturer, $submission->id, ['scores' => $assignment->rubricCriteria->mapWithKeys(fn ($criterion): array => [$criterion->id => 80])->all(), 'feedback' => 'Data contoh: prosedur sesuai, dokumentasi hasil dapat dibuat lebih rinci.']);
        }
    }
}
