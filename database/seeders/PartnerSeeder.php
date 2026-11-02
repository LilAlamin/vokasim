<?php

namespace Database\Seeders;

use App\Models\PartnerAgreement;
use App\Models\PartnerCompany;
use App\Models\PartnerUnit;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PartnerSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $company = PartnerCompany::factory()->create(['name' => 'Mitra Teknologi Demo (Data Contoh)']);
        PartnerUnit::factory()->for($company, 'company')->create(['name' => 'Unit Pengembangan Demo']);
        PartnerAgreement::factory()->for($company, 'company')->expiring()->create(['reference_number' => 'CONTOH-MOU-DEMO', 'notes' => 'Data contoh. Dokumen perjanjian belum diunggah.']);
    }
}
