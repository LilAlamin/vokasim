<?php

namespace Database\Factories;

use App\Models\PortfolioProject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @extends Factory<PortfolioProject>
 */
class PortfolioProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => User::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(4),
            'skills' => ['php', 'sql'],
            'project_url' => null,
            'completed_on' => now()->subWeek()->toDateString(),
            'is_published' => false,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => ['is_published' => true]);
    }

    public function withEvidence(): static
    {
        return $this->state(fn (): array => [
            'evidence_path' => 'portfolio-evidence/demo/'.Str::uuid().'.encrypted',
            'evidence_name' => 'bukti-proyek.pdf',
            'evidence_mime' => 'application/pdf',
            'evidence_size' => 31,
        ])->afterCreating(function (PortfolioProject $project): void {
            Storage::disk('local')->put($project->evidence_path, Crypt::encryptString('%PDF-1.4 Bukti proyek pengujian'));
        });
    }
}
