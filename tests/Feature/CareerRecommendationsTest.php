<?php

use App\Enums\UserRole;
use App\Models\Certificate;
use App\Models\PortfolioProject;
use App\Models\PracticumAssessment;
use App\Models\PracticumSubmission;
use App\Models\User;
use App\Services\CareerRecommendations;
use Livewire\Livewire;

test('career matching counts only the students verified current evidence and separates self declared project tags', function () {
    $this->freezeTime();
    $student = User::factory()->create();
    Certificate::factory()->for($student, 'student')->verified()->create(['skills' => ['PHP', 'php']]);
    Certificate::factory()->for($student, 'student')->expired()->create(['skills' => ['sql']]);
    Certificate::factory()->for($student, 'student')->revoked()->create(['skills' => ['git']]);
    Certificate::factory()->for($student, 'student')->create(['skills' => ['laravel']]);
    Certificate::factory()->for($student, 'student')->verified()->create(['skills' => ['sql'], 'verified_at' => null]);
    Certificate::factory()->verified()->create(['skills' => ['php', 'sql', 'git', 'laravel']]);
    PortfolioProject::factory()->for($student, 'student')->create(['skills' => ['SQL', 'Laravel', 'Git']]);

    $profile = app(CareerRecommendations::class)->forStudent($student);

    expect($profile['recommendations'][0])->toMatchArray([
        'key' => 'web-developer', 'match_percentage' => 25,
        'matched_skills' => ['php'], 'missing_skills' => ['laravel', 'sql', 'git'],
    ]);
    expect(array_keys($profile['evidence']))->toBe(['php']);
    expect($profile['declared_skills'])->toBe(['git', 'laravel', 'sql']);
});

test('rubric evidence includes the seventy percent boundary and excludes lower scores', function () {
    $student = User::factory()->create();
    $submission = PracticumSubmission::factory()->for($student, 'student')->graded()->create();
    PracticumAssessment::factory()->for($submission, 'submission')->create(['scores' => [
        ['id' => 1, 'title' => 'PHP', 'category' => 'hard_skill', 'weight' => 1, 'max_score' => 10, 'score' => 7],
        ['id' => 2, 'title' => 'SQL', 'category' => 'hard_skill', 'weight' => 1, 'max_score' => 100, 'score' => 69],
        ['id' => 3, 'title' => 'Git', 'category' => 'hard_skill', 'weight' => 1, 'max_score' => 0, 'score' => 0],
    ]]);

    $profile = app(CareerRecommendations::class)->forStudent($student);

    expect($profile['recommendations'][0])->toMatchArray(['key' => 'web-developer', 'matched_skills' => ['php'], 'match_percentage' => 25]);
    expect($profile['evidence']['php'][0])->toMatchArray([
        'source' => 'practicum', 'id' => $submission->assignment->practicum_module_id,
        'title' => $submission->assignment->title, 'detail' => 'Rubrik PHP · 7.0 / 10.0',
    ]);
    expect($profile['evidence'])->not->toHaveKey('sql')->not->toHaveKey('git');
});

test('superseded or ungraded submissions do not add competencies to recommendations', function () {
    $student = User::factory()->create();
    $old = PracticumSubmission::factory()->for($student, 'student')->graded()->create();
    PracticumAssessment::factory()->for($old, 'submission')->create(['scores' => [
        ['id' => 1, 'title' => 'php', 'category' => 'hard_skill', 'weight' => 1, 'max_score' => 10, 'score' => 10],
    ]]);
    PracticumSubmission::factory()->for($student, 'student')->for($old->assignment, 'assignment')->create(['attempt' => 2]);
    $ungraded = PracticumSubmission::factory()->for($student, 'student')->create();
    PracticumAssessment::factory()->for($ungraded, 'submission')->create(['scores' => [
        ['id' => 2, 'title' => 'sql', 'category' => 'hard_skill', 'weight' => 1, 'max_score' => 10, 'score' => 10],
    ]]);

    $profile = app(CareerRecommendations::class)->forStudent($student);

    expect($profile['evidence'])->toBe([]);
    expect(array_column($profile['recommendations'], 'match_percentage'))->each->toBe(0);
});

test('certificate evidence remains current through its expiration date', function () {
    $this->travelTo(now()->setDate(2026, 10, 31)->startOfDay());
    $student = User::factory()->create();
    Certificate::factory()->for($student, 'student')->verified()->create(['skills' => ['sql'], 'expires_on' => '2026-10-31']);
    $recommendations = app(CareerRecommendations::class);

    expect($recommendations->forStudent($student)['evidence'])->toHaveKey('sql');
    $this->travel(1)->days();

    expect($recommendations->forStudent($student)['evidence'])->toBe([]);
});

test('career page explains the source of scores and provides an actionable empty state', function () {
    $student = User::factory()->create();

    Livewire::actingAs($student)->test('pages::careers.index')->assertOk()
        ->assertSee('Cara rekomendasi dihitung')->assertSee('Mulai membangun bukti kompetensi')
        ->assertSee('0 dari 4 kompetensi didukung bukti.')->assertSee('Junior Web Developer')
        ->assertSee('Persentase ini bukan peluang diterima kerja');
});

test('career page links verified evidence while treating portfolio tags as self declared', function () {
    $student = User::factory()->create();
    $certificate = Certificate::factory()->for($student, 'student')->verified()->create(['title' => 'Capaian pengembangan web', 'skills' => ['php', 'laravel']]);
    PortfolioProject::factory()->for($student, 'student')->create(['skills' => ['git']]);

    $this->actingAs($student)->get(route('careers.index'))->assertOk()->assertSee('50')
        ->assertSee('2 dari 4 kompetensi didukung bukti.')->assertSee($certificate->title)
        ->assertSee(route('certificates.index', ['certificate' => $certificate->id]))
        ->assertSee('Tag proyek merupakan pernyataan mandiri');
});

test('career evidence never includes another students assessed work', function () {
    $student = User::factory()->create();
    PracticumAssessment::factory()->create(['scores' => [
        ['id' => 1, 'title' => 'php', 'category' => 'hard_skill', 'weight' => 1, 'max_score' => 10, 'score' => 10],
    ]]);

    expect(app(CareerRecommendations::class)->forStudent($student)['evidence'])->toBe([]);
});

test('career recommendations are available only to the signed in student', function (UserRole $role) {
    $this->get(route('careers.index'))->assertRedirectToRoute('login');
    $this->actingAs(User::factory()->create(['role' => $role]))->get(route('careers.index'))->assertForbidden();
})->with([UserRole::Dosen, UserRole::MentorDudi, UserRole::Admin]);
