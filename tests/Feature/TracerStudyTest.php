<?php

use App\Actions\BuildTracerAnalytics;
use App\Actions\SaveTracerResponse;
use App\Enums\UserRole;
use App\Models\PartnerCompany;
use App\Models\TracerResponse;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

function tracerSurveyInput(array $changes = []): array
{
    return [...[
        'graduation_year' => 2025, 'graduated_on' => '2025-06-30',
        'employment_status' => 'employed', 'employer_name' => 'Mitra Karier Uji', 'job_title' => 'Teknisi jaringan',
        'partner_company_id' => null, 'first_job_started_on' => '2025-09-30',
        'first_monthly_salary' => 6000000, 'field_alignment' => 'aligned',
        'skill_gaps' => ['Komunikasi', 'komunikasi', ' Bahasa Inggris '],
        'curriculum_feedback' => 'Tambahkan praktik komunikasi dan presentasi.',
        'consent' => true,
    ], ...$changes];
}

test('alumni save and update their own survey with computed wait time and profile program', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-31'));
    $student = User::factory()->create(['study_program' => 'D4 Teknik Mekatronika']);
    $other = User::factory()->create();

    Livewire::actingAs($student)->test('pages::tracer.survey')
        ->set('form', tracerSurveyInput(['user_id' => $other->id, 'study_program' => 'Forged program', 'wait_months' => 90]))
        ->set('skillGaps', 'Komunikasi, komunikasi, Bahasa Inggris')->set('consent', true)
        ->call('save')->assertHasNoErrors();
    $response = TracerResponse::query()->sole();

    $this->assertDatabaseHas('tracer_responses', ['user_id' => $student->id, 'study_program' => 'D4 Teknik Mekatronika', 'wait_months' => 3, 'first_monthly_salary' => 6000000]);
    expect($response->skill_gaps)->toBe(['komunikasi', 'bahasa inggris']);
    Livewire::actingAs($student)->test('pages::tracer.survey')->set('form.job_title', 'Teknisi senior')->set('consent', true)->call('save')->assertHasNoErrors();
    $this->assertDatabaseCount('tracer_responses', 1);
    $this->assertDatabaseHas('tracer_responses', ['id' => $response->id, 'job_title' => 'Teknisi senior']);
    Livewire::actingAs($other)->test('pages::tracer.survey')->assertSet('form.employer_name', '');
});

test('survey and executive analytics policies enforce their separate roles', function (UserRole $role, bool $survey, bool $analytics) {
    $user = User::factory()->create(['role' => $role]);

    expect(Gate::forUser($user)->allows('create', TracerResponse::class))->toBe($survey);
    expect(Gate::forUser($user)->allows('viewAny', TracerResponse::class))->toBe($analytics);
})->with([
    [UserRole::Mahasiswa, true, false], [UserRole::Dosen, false, false], [UserRole::MentorDudi, false, false], [UserRole::Admin, false, true],
]);

test('the executive page rejects students and the survey rejects lecturers', function () {
    $this->actingAs(User::factory()->create())->get(route('tracer.index'))->assertForbidden();
    $this->actingAs(User::factory()->dosen()->create())->get(route('tracer.survey'))->assertForbidden();
});

test('survey validates consent graduation employment and salary consistency', function (array $changes, string $field) {
    $this->travelTo(CarbonImmutable::parse('2026-10-31'));
    $student = User::factory()->create();
    $input = tracerSurveyInput($changes);

    Livewire::actingAs($student)->test('pages::tracer.survey')->set('form', $input)->set('consent', $input['consent'])
        ->call('save')->assertHasErrors([$field]);

    $this->assertDatabaseCount('tracer_responses', 0);
})->with([
    'consent required' => [['consent' => false], 'consent'],
    'graduation year mismatch' => [['graduation_year' => 2024], 'graduation_year'],
    'not graduated yet' => [['graduated_on' => '2027-01-01'], 'graduated_on'],
    'missing employer' => [['employer_name' => ''], 'employer_name'],
    'future first job' => [['first_job_started_on' => '2027-01-01'], 'first_job_started_on'],
    'salary without job' => [['employment_status' => 'seeking', 'first_job_started_on' => ''], 'first_monthly_salary'],
    'negative salary' => [['first_monthly_salary' => -1], 'first_monthly_salary'],
]);

test('a first job before graduation counts as zero wait and nonworking status clears current employer', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-31'));
    $student = User::factory()->create();
    $company = PartnerCompany::factory()->create();
    $response = app(SaveTracerResponse::class)->handle($student, tracerSurveyInput(['first_job_started_on' => '2025-01-01', 'partner_company_id' => $company->id]));

    expect($response->wait_months)->toBe(0);

    app(SaveTracerResponse::class)->handle($student, tracerSurveyInput(['employment_status' => 'seeking', 'partner_company_id' => $company->id]));

    $this->assertDatabaseHas('tracer_responses', ['user_id' => $student->id, 'employment_status' => 'seeking', 'employer_name' => null, 'job_title' => null, 'partner_company_id' => null, 'field_alignment' => null]);
});

test('tracer statistics report respondent denominators and ignore unanswered salary and alignment', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-31'));
    $admin = User::factory()->admin()->create();
    TracerResponse::factory()->create(['graduation_year' => 2025, 'wait_months' => 2, 'first_monthly_salary' => 6000000, 'skill_gaps' => ['komunikasi']]);
    TracerResponse::factory()->create(['graduation_year' => 2025, 'employment_status' => 'entrepreneur', 'wait_months' => 4, 'first_monthly_salary' => 4000000, 'field_alignment' => 'partial', 'skill_gaps' => ['komunikasi', 'bahasa inggris']]);
    TracerResponse::factory()->seeking()->create(['graduation_year' => 2025, 'skill_gaps' => []]);

    $analytics = app(BuildTracerAnalytics::class)->handle($admin);

    expect($analytics)->toMatchArray([
        'total_responses' => 3, 'working_count' => 2, 'absorption_percentage' => 66.7,
        'average_wait_months' => 3.0, 'wait_sample' => 2,
        'average_first_salary' => 5000000.0, 'salary_sample' => 2,
        'alignment_percentage' => 50.0, 'alignment_sample' => 2,
        'status_counts' => ['employed' => 1, 'entrepreneur' => 1, 'seeking' => 1, 'studying' => 0, 'not_seeking' => 0],
        'graduation_year_counts' => [2025 => 3], 'skill_gaps' => ['komunikasi' => 2, 'bahasa inggris' => 1],
    ]);
    $this->actingAs($admin)->get(route('tracer.index'))->assertOk()->assertSee('Hasil dari 3 responden')->assertSee('Rp 5.000.000');
});

test('analytics filters use the selected graduation year and program and preserve zero reported salaries', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-31'));
    $admin = User::factory()->admin()->create();
    TracerResponse::factory()->create(['graduation_year' => 2025, 'study_program' => 'Teknik Mesin', 'first_monthly_salary' => 0]);
    TracerResponse::factory()->create(['graduation_year' => 2024, 'study_program' => 'Teknik Mesin', 'first_monthly_salary' => 9000000]);
    TracerResponse::factory()->create(['graduation_year' => 2025, 'study_program' => 'Teknik Komputer', 'first_monthly_salary' => 7000000]);

    $analytics = app(BuildTracerAnalytics::class)->handle($admin, ['graduation_year' => 2025, 'study_program' => 'Teknik Mesin']);

    expect($analytics)->toMatchArray(['total_responses' => 1, 'salary_sample' => 1, 'average_first_salary' => 0.0]);
    Livewire::actingAs($admin)->test('pages::tracer.index')->set('graduationYear', '2025')->set('studyProgram', 'Teknik Mesin')->assertSee('Hasil dari 1 responden')->assertSee('Rp 0');
});

test('empty analytics show missing data instead of invented zero averages', function () {
    $admin = User::factory()->admin()->create();

    $analytics = app(BuildTracerAnalytics::class)->handle($admin);

    expect($analytics)->toMatchArray(['total_responses' => 0, 'absorption_percentage' => null, 'average_wait_months' => null, 'average_first_salary' => null, 'alignment_percentage' => null, 'skill_gaps' => []]);
    $this->actingAs($admin)->get(route('tracer.index'))->assertOk()->assertSee('Belum ada jawaban pada kelompok ini');
});
