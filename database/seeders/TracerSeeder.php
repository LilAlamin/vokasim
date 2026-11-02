<?php

namespace Database\Seeders;

use App\Models\TracerResponse;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TracerSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        TracerResponse::factory()->for(User::factory()->state(['name' => 'Alumni Bekerja Demo (Data Contoh)']))->create(['employer_name' => 'Perusahaan Demo (Data Contoh)']);
        TracerResponse::factory()->for(User::factory()->state(['name' => 'Alumni Pencari Kerja Demo (Data Contoh)']))->seeking()->create();
    }
}
