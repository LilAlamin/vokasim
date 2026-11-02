<?php

namespace Database\Factories;

use App\Models\PartnerAgreement;
use App\Models\PartnerCompany;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PartnerAgreement> */
class PartnerAgreementFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'partner_company_id' => PartnerCompany::factory(),
            'reference_number' => fake()->unique()->bothify('MOU-####-????'),
            'kind' => 'mou',
            'signed_on' => today()->subMonths(2)->toDateString(),
            'starts_on' => today()->subMonth()->toDateString(),
            'expires_on' => today()->addYear()->toDateString(),
            'document_path' => null,
            'notes' => null,
            'is_active' => true,
        ];
    }

    public function expiring(): static
    {
        return $this->state(fn (): array => ['expires_on' => today()->addDays(15)->toDateString()]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['expires_on' => today()->subDay()->toDateString()]);
    }
}
