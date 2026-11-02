<?php

namespace Database\Factories;

use App\Models\PracticumMaterial;
use App\Models\PracticumModule;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PracticumMaterial> */
class PracticumMaterialFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'practicum_module_id' => PracticumModule::factory(),
            'uploaded_by' => fn (array $attributes): int => PracticumModule::query()->whereKey($attributes['practicum_module_id'])->firstOrFail()->lecturer_id,
            'title' => 'Panduan keselamatan laboratorium', 'kind' => 'k3',
            'file_path' => 'practicum-materials/'.fake()->uuid().'.pdf', 'original_name' => 'panduan-k3.pdf', 'mime_type' => 'application/pdf', 'size' => 1000,
        ];
    }
}
