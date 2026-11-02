<?php

namespace Database\Factories;

use App\Models\PartnerCompany;
use App\Models\PartnerUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PartnerUnit> */
class PartnerUnitFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'partner_company_id' => PartnerCompany::factory(),
            'name' => 'Unit '.fake()->unique()->word(),
            'address' => fake()->streetAddress(),
            'latitude' => -6.2,
            'longitude' => 106.8166667,
            'radius_meters' => 100,
            'timezone' => 'Asia/Jakarta',
            'internship_quota' => 5,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
