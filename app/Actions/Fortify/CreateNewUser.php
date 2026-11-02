<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): User
    {
        $role = $input['role'] ?? UserRole::Mahasiswa->value;

        Validator::make($input, [
            ...$this->profileRules(),
            'role' => ['required', 'string', Rule::in([
                UserRole::Mahasiswa->value,
                UserRole::Dosen->value,
                UserRole::MentorDudi->value,
            ])],
            'identifier_number' => [
                $role === UserRole::Mahasiswa->value || $role === UserRole::Dosen->value ? 'required' : 'nullable',
                'string',
                'max:50',
            ],
            'study_program' => [
                $role === UserRole::Mahasiswa->value || $role === UserRole::Dosen->value ? 'required' : 'nullable',
                'string',
                'max:100',
            ],
            'institution_or_company' => [
                $role === UserRole::MentorDudi->value ? 'required' : 'nullable',
                'string',
                'max:150',
            ],
            'phone' => ['nullable', 'string', 'max:25'],
            'password' => $this->passwordRules(),
        ], [
            'identifier_number.required' => $role === UserRole::Mahasiswa->value
                ? 'Nomor Induk Mahasiswa (NIM) wajib diisi.'
                : 'NIP / NIDN wajib diisi.',
            'study_program.required' => 'Program studi / kejuruan wajib diisi.',
            'institution_or_company.required' => 'Nama perusahaan / mitra industri wajib diisi.',
        ], [
            'identifier_number' => $role === UserRole::Mahasiswa->value ? 'NIM' : 'NIP / NIDN',
            'study_program' => 'Program Studi',
            'institution_or_company' => 'Nama Perusahaan / Institusi',
            'phone' => 'Nomor WhatsApp / Telepon',
        ])->validate();

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'role' => $input['role'] ?? UserRole::Mahasiswa->value,
            'identifier_number' => $input['identifier_number'] ?? null,
            'study_program' => $input['study_program'] ?? null,
            'institution_or_company' => $input['institution_or_company'] ?? null,
            'phone' => $input['phone'] ?? null,
            'password' => $input['password'],
            'email_verified_at' => app()->environment('local') ? now() : null,
        ]);
    }
}
