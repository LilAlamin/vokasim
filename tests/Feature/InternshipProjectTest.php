<?php

use App\Enums\LogbookStatus;
use App\Enums\UserRole;
use App\Models\InternshipAttendance;
use App\Models\InternshipLogbook;
use App\Models\InternshipPlacement;
use App\Models\InternshipProject;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

function internshipProjectInput(array $changes = []): array
{
    return [...[
        'title' => 'Pemeriksaan panel kontrol',
        'description' => 'Memeriksa panel dan menyusun laporan pemeliharaan mesin.',
        'starts_on' => '2026-10-31', 'due_on' => '2026-11-02',
        'target_minutes' => 480, 'completed_minutes' => 120,
        'feedback' => 'Tahap pemeriksaan awal telah ditinjau.',
    ], ...$changes];
}

test('mentors set project targets and update their students progress', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-31 08:00', 'Asia/Jakarta'));
    $placement = InternshipPlacement::factory()->create();
    $page = Livewire::actingAs($placement->mentor)->test('pages::industry.index')->set('placementId', (string) $placement->id)
        ->set('form', internshipProjectInput())->call('saveProject')->assertHasNoErrors()->assertSee('25% target');
    $project = InternshipProject::query()->sole();

    $page->call('editProject', $project->id)->set('form.completed_minutes', 480)->call('saveProject')->assertHasNoErrors()->assertSee('100% target');

    $this->assertDatabaseHas('internship_projects', ['id' => $project->id, 'internship_placement_id' => $placement->id, 'mentor_id' => $placement->mentor_id, 'completed_minutes' => 480]);
    $this->actingAs($placement->student)->get(route('industry.index'))->assertOk()->assertSee('Pemeriksaan panel kontrol')->assertDontSee('Tetapkan proyek / target kerja');
});

test('project write access requires the assigned mentor', function (UserRole $role, bool $allowed) {
    $placement = InternshipPlacement::factory()->create();
    $user = match ($role) {
        UserRole::Mahasiswa => $placement->student,
        UserRole::Dosen => $placement->lecturer,
        UserRole::MentorDudi => $placement->mentor,
        UserRole::Admin => User::factory()->admin()->create(),
    };

    expect(Gate::forUser($user)->allows('create', [InternshipProject::class, $placement]))->toBe($allowed);
})->with([[UserRole::Mahasiswa, false], [UserRole::Dosen, false], [UserRole::MentorDudi, true], [UserRole::Admin, false]]);

test('students cannot forge project progress and mentors cannot change another placements project', function () {
    $this->freezeTime();
    $project = InternshipProject::factory()->create();

    Livewire::actingAs($project->placement->student)->test('pages::industry.index')->call('editProject', $project->id)->assertForbidden();
    Livewire::actingAs(User::factory()->mentorDudi()->create())->test('pages::industry.index')->call('editProject', $project->id)->assertNotFound();

    $this->assertDatabaseHas('internship_projects', ['id' => $project->id, 'completed_minutes' => 600]);
});

test('project progress and dates must fit the target and placement period', function (array $changes, string $error) {
    $this->travelTo(CarbonImmutable::parse('2026-10-31 08:00', 'Asia/Jakarta'));
    $placement = InternshipPlacement::factory()->create(['starts_on' => '2026-10-01', 'ends_on' => '2026-12-31']);

    Livewire::actingAs($placement->mentor)->test('pages::industry.index')->set('placementId', (string) $placement->id)
        ->set('form', internshipProjectInput($changes))->call('saveProject')->assertHasErrors([$error]);

    $this->assertDatabaseCount('internship_projects', 0);
})->with([
    'above target' => [['completed_minutes' => 481], 'completed_minutes'],
    'negative work' => [['completed_minutes' => -1], 'completed_minutes'],
    'before internship' => [['starts_on' => '2026-09-01'], 'starts_on'],
    'after internship' => [['due_on' => '2027-01-01'], 'due_on'],
    'work before project starts' => [['starts_on' => '2026-11-01'], 'completed_minutes'],
]);

test('industry recap shows real approved hours and keeps unrelated students out', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-31 17:00', 'Asia/Jakarta'));
    $placement = InternshipPlacement::factory()->create();
    InternshipAttendance::factory()->for($placement, 'placement')->completed()->create();
    InternshipAttendance::factory()->for($placement, 'placement')->create(['work_date' => '2026-10-30']);
    InternshipLogbook::factory()->for($placement, 'placement')->create(['work_date' => '2026-10-29', 'status' => LogbookStatus::Verified, 'effective_minutes' => 120]);
    InternshipLogbook::factory()->for($placement, 'placement')->create(['work_date' => '2026-10-30', 'status' => LogbookStatus::MentorApproved, 'effective_minutes' => 60]);
    InternshipLogbook::factory()->for($placement, 'placement')->create(['work_date' => '2026-10-31', 'status' => LogbookStatus::PendingMentor, 'effective_minutes' => 30]);
    $outsider = InternshipPlacement::factory()->create(['company_name' => 'Unrelated private workplace']);
    InternshipProject::factory()->for($outsider, 'placement')->create(['title' => 'Unrelated private project']);

    $this->actingAs($placement->mentor)->get(route('industry.index'))->assertOk()
        ->assertSee('3.0')->assertSee('2.0 / 600')->assertSee('1 / 2 hari')->assertSee('2 logbook menunggu')
        ->assertDontSee('Unrelated private workplace')->assertDontSee('Unrelated private project');
});
