<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $this->call([
            PracticumSeeder::class,
            InternshipSeeder::class,
            PartnerSeeder::class,
            PortfolioProjectSeeder::class,
            CertificateSeeder::class,
            IndustryOperationsSeeder::class,
            TracerSeeder::class,
            InterviewSessionSeeder::class,
        ]);
    }
}
