<?php

use App\Enums\UserRole;
use App\Models\InternshipAttendance;
use App\Models\InternshipPlacement;
use App\Models\User;
use Livewire\Livewire;

function placementFormData(User $student, User $mentor, User $lecturer): array
{
    return ['student_id' => $student->id, 'mentor_id' => $mentor->id, 'lecturer_id' => $lecturer->id, 'company_name' => 'PT Vokasi Industri', 'address' => 'Jakarta', 'latitude' => -6.2, 'longitude' => 106.8166667, 'radius_meters' => 100, 'timezone' => 'Asia/Jakarta', 'starts_on' => '2026-10-01', 'ends_on' => '2026-12-31', 'target_hours' => 600];
}

test('guests must sign in before accessing internship pages', function (string $routeName) {
    $this->get(route($routeName))->assertRedirect(route('login'));
})->with(['internships.placements', 'internships.logbook', 'internships.reviews']);

test('an admin can create a placement and the student sees the assignment', function () {
    $admin = User::factory()->admin()->create();
    $student = User::factory()->create();
    $mentor = User::factory()->mentorDudi()->create();
    $lecturer = User::factory()->dosen()->create();

    Livewire::actingAs($admin)->test('pages::internships.placements')
        ->set('form', placementFormData($student, $mentor, $lecturer))->call('save')->assertHasNoErrors();

    $this->assertDatabaseHas('internship_placements', ['student_id' => $student->id, 'mentor_id' => $mentor->id, 'lecturer_id' => $lecturer->id, 'radius_meters' => 100]);
    $this->actingAs($student)->get(route('internships.logbook'))->assertOk()->assertSee('PT Vokasi Industri')->assertSee($mentor->name)->assertSee($lecturer->name);
});

test('non admins cannot configure placements', function (UserRole $role) {
    $user = User::factory()->create(['role' => $role]);
    $this->actingAs($user)->get(route('internships.placements'))->assertForbidden();
    $this->assertDatabaseCount('internship_placements', 0);
})->with([UserRole::Mahasiswa, UserRole::Dosen, UserRole::MentorDudi]);

test('placements reject the wrong reviewer role and overlapping student periods', function () {
    $this->travelTo(now()->setDate(2026, 10, 31));
    $placement = InternshipPlacement::factory()->create(['starts_on' => '2026-10-01', 'ends_on' => '2026-12-31']);
    $admin = User::factory()->admin()->create();
    $form = placementFormData($placement->student, $placement->mentor, $placement->lecturer);
    $component = Livewire::actingAs($admin)->test('pages::internships.placements')->set('form', $form);
    $component->set('form.mentor_id', $placement->student_id)->call('save')->assertHasErrors(['mentor_id']);
    $component->set('form.mentor_id', $placement->mentor_id)->call('save')->assertHasErrors(['student_id'])->assertSee('Mahasiswa sudah memiliki penempatan pada periode tersebut.');
    $this->assertDatabaseCount('internship_placements', 1);
});

test('admin can correct an unused placement but cannot change a recorded geofence', function () {
    $placement = InternshipPlacement::factory()->create();
    $admin = User::factory()->admin()->create();
    $component = Livewire::actingAs($admin)->test('pages::internships.placements')->call('edit', $placement->id)
        ->set('form.radius_meters', 80)->call('save')->assertHasNoErrors();
    $this->assertDatabaseHas('internship_placements', ['id' => $placement->id, 'radius_meters' => 80]);

    InternshipAttendance::factory()->for($placement, 'placement')->create();
    $component->call('edit', $placement->id)->set('form.radius_meters', 1000)->call('save')->assertHasErrors(['placement']);
    $this->assertDatabaseHas('internship_placements', ['id' => $placement->id, 'radius_meters' => 80]);
});

test('placement coordinates and dates must be valid', function (string $field, mixed $value) {
    $admin = User::factory()->admin()->create();
    $form = placementFormData(User::factory()->create(), User::factory()->mentorDudi()->create(), User::factory()->dosen()->create());
    Livewire::actingAs($admin)->test('pages::internships.placements')->set('form', $form)->set('form.'.$field, $value)->call('save')->assertHasErrors([$field]);
    $this->assertDatabaseCount('internship_placements', 0);
})->with(['latitude' => ['latitude', 91], 'longitude' => ['longitude', 181], 'radius' => ['radius_meters', 0], 'reverse dates' => ['ends_on', '2026-09-30'], 'timezone' => ['timezone', 'UTC']]);
