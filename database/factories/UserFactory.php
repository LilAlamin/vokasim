<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'role' => UserRole::Mahasiswa,
            'is_active' => true,
            'gpa' => null,
            'semester' => null,
            'graduation_year' => null,
            'identifier_number' => fake()->numerify('2104411#####'),
            'study_program' => 'D4 Teknik Informatika',
            'institution_or_company' => 'Politeknik Negeri',
            'phone' => fake()->phoneNumber(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * Indicate that the user is a lecturer.
     */
    public function dosen(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Dosen,
            'identifier_number' => fake()->numerify('198507##########'),
            'study_program' => 'Teknik Manufaktur & Otomasi',
        ]);
    }

    /**
     * Indicate that the user is an industry mentor.
     */
    public function mentorDudi(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::MentorDudi,
            'identifier_number' => 'Supervisor Maintenance',
            'institution_or_company' => 'PT Astra Honda Motor',
        ]);
    }

    /**
     * Indicate that the user is an administrator.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Admin,
            'identifier_number' => 'ADMIN-001',
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
