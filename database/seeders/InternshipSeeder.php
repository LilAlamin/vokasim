<?php

namespace Database\Seeders;

use App\Models\InternshipAttendance;
use App\Models\InternshipLogbook;
use App\Models\InternshipPlacement;
use Illuminate\Database\Seeder;

class InternshipSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $placement = InternshipPlacement::factory()->create(['company_name' => 'Mitra DUDI Demo (Data Contoh)']);
        InternshipAttendance::factory()->for($placement, 'placement')->completed()->create();
        InternshipLogbook::factory()->for($placement, 'placement')->create();
    }
}
