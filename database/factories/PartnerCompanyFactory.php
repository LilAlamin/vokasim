<?php

namespace Database\Factories;

use App\Models\PartnerCompany;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PartnerCompany> */
class PartnerCompanyFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'sector' => 'Teknologi informasi',
            'address' => fake()->streetAddress(),
            'city' => 'Jakarta',
            'province' => 'DKI Jakarta',
            'latitude' => -6.2,
            'longitude' => 106.8166667,
            'contact_name' => fake()->name(),
            'contact_email' => fake()->safeEmail(),
            'contact_phone' => fake()->phoneNumber(),
            'internship_quota' => 10,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
