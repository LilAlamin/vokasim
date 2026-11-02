<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class CertificateReviewSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(CertificateSeeder::class);
    }
}
