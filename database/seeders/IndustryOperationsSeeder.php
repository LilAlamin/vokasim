<?php

namespace Database\Seeders;

use App\Models\IndustryEvaluation;
use App\Models\InternshipLeaveRequest;
use App\Models\InternshipPlacement;
use App\Models\InternshipProject;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class IndustryOperationsSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $placement = InternshipPlacement::factory()->create(['company_name' => 'Pendampingan Industri Demo (Data Contoh)']);
        InternshipProject::factory()->for($placement, 'placement')->create(['title' => 'Proyek Penerapan SOP Demo (Data Contoh)']);
        IndustryEvaluation::factory()->for($placement, 'placement')->create();
        InternshipLeaveRequest::factory()->for($placement, 'placement')->create();
    }
}
