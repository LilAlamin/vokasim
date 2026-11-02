<?php

use App\Actions\NotifyVokasimReminders;
use App\Models\PartnerAgreement;
use App\Models\PartnerCompany;
use App\Models\PracticumAssignment;
use App\Models\PracticumEnrollment;
use App\Models\PracticumModule;
use App\Models\PracticumSession;
use App\Models\PracticumSubmission;
use App\Models\User;
use Carbon\CarbonImmutable;

test('deadline reminders target active enrolled students with missing or revision requested work only once', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-31 08:00:00', 'UTC'));
    $module = PracticumModule::factory()->create(['timezone' => 'Asia/Jakarta']);
    $pending = PracticumEnrollment::factory()->for($module, 'module')->create();
    $submitted = PracticumEnrollment::factory()->for($module, 'module')->create();
    $revision = PracticumEnrollment::factory()->for($module, 'module')->create();
    $inactive = PracticumEnrollment::factory()->for($module, 'module')->create(['status' => 'inactive']);
    $assignment = PracticumAssignment::factory()->for($module, 'module')->create(['due_at' => now('UTC')->addHours(24)]);
    PracticumSubmission::factory()->for($assignment, 'assignment')->for($submitted->student, 'student')->create();
    PracticumSubmission::factory()->for($assignment, 'assignment')->for($revision->student, 'student')->revisionRequested()->create();

    $this->artisan('vokasim:send-reminders')->expectsOutput('2 notifikasi pengingat baru disimpan.')->assertSuccessful();
    $this->artisan('vokasim:send-reminders')->expectsOutput('0 notifikasi pengingat baru disimpan.')->assertSuccessful();

    $notice = $pending->student->notifications()->sole();
    expect($notice->data)->toMatchArray(['title' => 'Tenggat laporan praktikum mendekat', 'route' => 'practicums.assignments.show', 'parameters' => ['module' => $module->id, 'assignment' => $assignment->id]]);
    expect($notice->data['message'])->toContain('01 Nov 2026 15:00 WIB');
    expect($revision->student->notifications()->where('data->title', 'Tenggat laporan praktikum mendekat')->count())->toBe(1);
    expect($submitted->student->notifications()->where('data->title', 'Tenggat laporan praktikum mendekat')->count())->toBe(0);
    expect($inactive->student->notifications()->count())->toBe(0);
});

test('past far future and unpublished assignments do not produce deadline reminders', function () {
    $this->freezeTime();
    $module = PracticumModule::factory()->create();
    $enrollment = PracticumEnrollment::factory()->for($module, 'module')->create();
    PracticumAssignment::factory()->for($module, 'module')->create(['due_at' => now('UTC')->subMinute()]);
    PracticumAssignment::factory()->for($module, 'module')->create(['due_at' => now('UTC')->addHours(25)]);
    $draft = PracticumModule::factory()->create(['published' => false]);
    PracticumEnrollment::factory()->for($draft, 'module')->for($enrollment->student, 'student')->create();
    PracticumAssignment::factory()->for($draft, 'module')->create(['due_at' => now('UTC')->addHour()]);

    $created = app(NotifyVokasimReminders::class)->handle();

    expect($created)->toBe(0);
    expect($enrollment->student->notifications()->count())->toBe(0);
});

test('upcoming examination reminders reach the assigned lecturer and active students but exclude canceled sessions', function () {
    $this->freezeTime();
    $module = PracticumModule::factory()->create();
    $active = PracticumEnrollment::factory()->for($module, 'module')->create();
    $inactive = PracticumEnrollment::factory()->for($module, 'module')->create(['status' => 'inactive']);
    $other = User::factory()->dosen()->create();
    $exam = PracticumSession::factory()->for($module, 'module')->create(['title' => 'Ujian kompetensi CNC', 'is_exam' => true, 'starts_at' => now('UTC')->addHours(48), 'ends_at' => now('UTC')->addHours(50)]);
    PracticumSession::factory()->for($module, 'module')->upcoming()->create(['is_exam' => true, 'cancelled' => true]);
    PracticumSession::factory()->for($module, 'module')->upcoming()->create(['is_exam' => false]);
    PracticumSession::factory()->for($module, 'module')->completed()->create(['is_exam' => true]);

    $created = app(NotifyVokasimReminders::class)->handle();

    expect($created)->toBe(2);
    expect($module->lecturer->notifications()->sole()->data['message'])->toContain($exam->title);
    expect($active->student->notifications()->sole()->data['route'])->toBe('practicums.show');
    expect($inactive->student->notifications()->count())->toBe(0);
    expect($other->notifications()->count())->toBe(0);
    expect(app(NotifyVokasimReminders::class)->handle())->toBe(0);
});

test('agreement expiry reminders include the thirty day boundary and go only to active administrators', function () {
    $this->freezeTime();
    $administrator = User::factory()->admin()->create();
    $inactive = User::factory()->admin()->inactive()->create();
    $lecturer = User::factory()->dosen()->create();
    $agreement = PartnerAgreement::factory()->create(['expires_on' => today()->addDays(30)->toDateString()]);
    PartnerAgreement::factory()->create(['expires_on' => today()->addDays(31)->toDateString()]);
    PartnerAgreement::factory()->expired()->create();
    PartnerAgreement::factory()->expiring()->create(['is_active' => false]);
    PartnerAgreement::factory()->for(PartnerCompany::factory()->create(['is_active' => false]), 'company')->expiring()->create();
    PartnerAgreement::factory()->create(['starts_on' => today()->addDays(3)->toDateString(), 'expires_on' => today()->addDays(25)->toDateString()]);

    $created = app(NotifyVokasimReminders::class)->handle();

    expect($created)->toBe(1);
    $notice = $administrator->notifications()->sole();
    expect($notice->data)->toMatchArray(['category' => 'partnership', 'route' => 'partners.index']);
    expect($notice->data['message'])->toContain($agreement->company->name);
    expect($inactive->notifications()->count())->toBe(0);
    expect($lecturer->notifications()->count())->toBe(0);
    expect(app(NotifyVokasimReminders::class)->handle())->toBe(0);
});

test('a changed deadline produces a new reminder while an unchanged deadline stays deduplicated', function () {
    $this->freezeTime();
    $module = PracticumModule::factory()->create();
    $enrollment = PracticumEnrollment::factory()->for($module, 'module')->create();
    $assignment = PracticumAssignment::factory()->for($module, 'module')->create(['due_at' => now('UTC')->addHours(12)]);
    $reminders = app(NotifyVokasimReminders::class);
    $reminders->handle();

    $assignment->update(['due_at' => now('UTC')->addHours(20)]);
    $created = $reminders->handle();

    expect($created)->toBe(1);
    expect($enrollment->student->notifications()->count())->toBe(2);
    expect($reminders->handle())->toBe(0);
});

test('inactive student accounts do not receive otherwise eligible reminders', function () {
    $this->freezeTime();
    $module = PracticumModule::factory()->create();
    $student = User::factory()->inactive()->create();
    PracticumEnrollment::factory()->for($module, 'module')->for($student, 'student')->create();
    PracticumAssignment::factory()->for($module, 'module')->create(['due_at' => now('UTC')->addHour()]);

    expect(app(NotifyVokasimReminders::class)->handle())->toBe(0);
    expect($student->notifications()->count())->toBe(0);
});
