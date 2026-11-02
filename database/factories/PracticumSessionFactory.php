<?php

namespace Database\Factories;

use App\Models\PracticumModule;
use App\Models\PracticumSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PracticumSession> */
class PracticumSessionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'practicum_module_id' => PracticumModule::factory(), 'title' => 'Pengenalan alat dan K3', 'room' => 'Laboratorium A',
            'starts_at' => now('UTC')->subMinutes(5), 'ends_at' => now('UTC')->addHours(2), 'is_exam' => false, 'cancelled' => false,
        ];
    }

    public function upcoming(): static
    {
        return $this->state(fn (): array => ['starts_at' => now('UTC')->addDay(), 'ends_at' => now('UTC')->addDay()->addHours(2)]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => ['starts_at' => now('UTC')->subDay(), 'ends_at' => now('UTC')->subDay()->addHours(2)]);
    }
}
