<?php

use App\Enums\UserRole;
use App\Models\User;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new mahasiswa can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Budi Santoso',
        'email' => 'budi@vokasi.ac.id',
        'role' => UserRole::Mahasiswa->value,
        'identifier_number' => '210441100012',
        'study_program' => 'D4 Teknik Informatika',
        'phone' => '081234567890',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();

    $user = User::where('email', 'budi@vokasi.ac.id')->first();
    expect($user)->not->toBeNull()
        ->and($user->role)->toBe(UserRole::Mahasiswa)
        ->and($user->identifier_number)->toBe('210441100012')
        ->and($user->study_program)->toBe('D4 Teknik Informatika')
        ->and($user->isMahasiswa())->toBeTrue();
});

test('new dosen can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Dr. Ir. Hendra, M.T.',
        'email' => 'hendra@vokasi.ac.id',
        'role' => UserRole::Dosen->value,
        'identifier_number' => '198507202012121002',
        'study_program' => 'Teknik Manufaktur & Otomasi',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();

    $user = User::where('email', 'hendra@vokasi.ac.id')->first();
    expect($user)->not->toBeNull()
        ->and($user->role)->toBe(UserRole::Dosen)
        ->and($user->identifier_number)->toBe('198507202012121002')
        ->and($user->isDosen())->toBeTrue();
});

test('new mentor dudi can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Rina Wijaya',
        'email' => 'rina@pt-industri.com',
        'role' => UserRole::MentorDudi->value,
        'institution_or_company' => 'PT Astra Honda Motor',
        'identifier_number' => 'Supervisor Maintenance',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();

    $user = User::where('email', 'rina@pt-industri.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->role)->toBe(UserRole::MentorDudi)
        ->and($user->institution_or_company)->toBe('PT Astra Honda Motor')
        ->and($user->isMentorDudi())->toBeTrue();
});
