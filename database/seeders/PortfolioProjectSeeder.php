<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\PortfolioProject;
use App\Models\User;
use Illuminate\Database\Seeder;

class PortfolioProjectSeeder extends Seeder
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

        if (! PortfolioProject::query()->where('student_id', $student->id)->where('title', 'Aplikasi Inventaris Bengkel (Data Contoh)')->exists()) {
            PortfolioProject::factory()->for($student, 'student')->create([
                'title' => 'Aplikasi Inventaris Bengkel (Data Contoh)',
                'description' => 'Contoh portofolio: merancang pencatatan peminjaman alat bengkel, membuat antarmuka web, dan menyiapkan laporan pengembalian. Ganti uraian ini dengan proyek dan hasil nyata Anda.',
                'skills' => ['php', 'laravel', 'sql', 'git'],
            ]);
        }
    }
}
