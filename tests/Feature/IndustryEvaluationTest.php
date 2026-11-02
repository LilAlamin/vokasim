<?php

use App\Actions\SaveIndustryEvaluation;
use App\Enums\UserRole;
use App\Models\IndustryEvaluation;
use App\Models\InternshipPlacement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

function industryEvaluationInput(array $changes = []): array
{
    return [...[
        'period_type' => 'monthly', 'period_month' => '2026-10',
        'work_quality' => 5, 'tool_mastery' => 4, 'discipline' => 3, 'teamwork' => 2, 'professional_ethics' => 1,
        'feedback' => 'Hasil kerja teknis baik, komunikasi perlu ditingkatkan.',
        'improvement_plan' => 'Sampaikan laporan progres kepada tim setiap akhir hari.',
    ], ...$changes];
}

test('assigned mentors publish rubric scores and students see the calculated evaluation', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-31 08:00', 'Asia/Jakarta'));
    $placement = InternshipPlacement::factory()->create();

    Livewire::actingAs($placement->mentor)->test('pages::internships.evaluations')->set('placementId', (string) $placement->id)
        ->set('form', industryEvaluationInput(['final_score' => 100, 'mentor_id' => $placement->student_id]))->call('save')->assertHasNoErrors();

    $this->assertDatabaseHas('industry_evaluations', ['internship_placement_id' => $placement->id, 'mentor_id' => $placement->mentor_id, 'final_score' => 60, 'revision' => 1]);
    $this->actingAs($placement->student)->get(route('internships.evaluations'))->assertOk()->assertSee('60 / 100')->assertSee('Sampaikan laporan progres');
});

test('saving the same period updates one evaluation and records its revision', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-31 08:00', 'Asia/Jakarta'));
    $placement = InternshipPlacement::factory()->create();
    $evaluation = app(SaveIndustryEvaluation::class)->handle($placement->mentor, $placement->id, industryEvaluationInput());

    Livewire::actingAs($placement->mentor)->test('pages::internships.evaluations')->call('editEvaluation', $evaluation->id)
        ->set('form.professional_ethics', 5)->call('save')->assertHasNoErrors();

    $this->assertDatabaseCount('industry_evaluations', 1);
    $this->assertDatabaseHas('industry_evaluations', ['id' => $evaluation->id, 'professional_ethics' => 5, 'final_score' => 76, 'revision' => 2]);
});

test('the rubric write policy permits only the assigned industry mentor', function (UserRole $role, bool $allowed) {
    $placement = InternshipPlacement::factory()->create();
    $user = match ($role) {
        UserRole::Mahasiswa => $placement->student,
        UserRole::Dosen => $placement->lecturer,
        UserRole::MentorDudi => $placement->mentor,
        UserRole::Admin => User::factory()->admin()->create(),
    };

    expect(Gate::forUser($user)->allows('create', [IndustryEvaluation::class, $placement]))->toBe($allowed);
})->with([[UserRole::Mahasiswa, false], [UserRole::Dosen, false], [UserRole::MentorDudi, true], [UserRole::Admin, false]]);

test('mentors cannot evaluate another mentors student or read unrelated scores', function () {
    $placement = InternshipPlacement::factory()->create();
    IndustryEvaluation::factory()->for($placement, 'placement')->create(['feedback' => 'Private evaluation from another team']);
    $other = User::factory()->mentorDudi()->create();

    Livewire::actingAs($other)->test('pages::internships.evaluations')->assertDontSee('Private evaluation from another team')
        ->set('placementId', (string) $placement->id)->set('form', industryEvaluationInput())->call('save')->assertNotFound();

    $this->assertDatabaseCount('industry_evaluations', 1);
});

test('invalid rubric scores and out of period evaluations are rejected', function (array $changes, string $field) {
    $this->travelTo(CarbonImmutable::parse('2026-10-31 08:00', 'Asia/Jakarta'));
    $placement = InternshipPlacement::factory()->create(['starts_on' => '2026-10-01', 'ends_on' => '2026-12-31']);

    Livewire::actingAs($placement->mentor)->test('pages::internships.evaluations')->set('placementId', (string) $placement->id)
        ->set('form', industryEvaluationInput($changes))->call('save')->assertHasErrors([$field]);

    $this->assertDatabaseCount('industry_evaluations', 0);
})->with([
    'missing score' => [['work_quality' => ''], 'work_quality'],
    'above rubric' => [['tool_mastery' => 6], 'tool_mastery'],
    'fractional score' => [['discipline' => 3.5], 'discipline'],
    'before placement' => [['period_month' => '2026-09'], 'period_month'],
    'future month' => [['period_month' => '2026-11'], 'period_month'],
    'premature final' => [['period_type' => 'final'], 'period_type'],
    'missing feedback' => [['feedback' => ''], 'feedback'],
]);

test('final evaluations are available on the final internship day and escape feedback', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-31 08:00', 'Asia/Jakarta'));
    $placement = InternshipPlacement::factory()->create(['starts_on' => '2026-07-01', 'ends_on' => '2026-10-31']);
    $feedback = '<script>alert("evaluation")</script> Catatan mentor.';

    app(SaveIndustryEvaluation::class)->handle($placement->mentor, $placement->id, industryEvaluationInput(['period_type' => 'final', 'feedback' => $feedback]));

    $this->actingAs($placement->student)->get(route('internships.evaluations'))->assertOk()->assertSee('Evaluasi akhir magang')
        ->assertSee($feedback)->assertDontSee($feedback, false);
    $this->assertDatabaseHas('industry_evaluations', ['internship_placement_id' => $placement->id, 'period_type' => 'final', 'period_month' => '2026-10-01 00:00:00']);
});
