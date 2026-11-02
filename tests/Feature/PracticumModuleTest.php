<?php

use App\Enums\UserRole;
use App\Models\PracticumAssignment;
use App\Models\PracticumEnrollment;
use App\Models\PracticumMaterial;
use App\Models\PracticumModule;
use App\Models\PracticumSubmission;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

function practicumModuleInput(User $lecturer): array
{
    return ['lecturer_id' => $lecturer->id, 'code' => 'TM-301-A-2026', 'title' => 'Praktikum Manufaktur', 'study_program' => 'Teknik Mesin', 'semester' => 3, 'skill_area' => 'Manufaktur', 'description' => 'Menerapkan proses manufaktur presisi dan prosedur K3.', 'timezone' => 'Asia/Jakarta', 'starts_on' => '2026-10-01', 'ends_on' => '2027-02-28', 'published' => true];
}

test('guests must authenticate before accessing practicum pages and private files', function () {
    $module = PracticumModule::factory()->create();
    $assignment = PracticumAssignment::factory()->for($module, 'module')->create();
    $material = PracticumMaterial::factory()->for($module, 'module')->create();
    $submission = PracticumSubmission::factory()->for($assignment, 'assignment')->create();

    foreach ([route('practicums.index'), route('practicums.show', $module), route('practicums.assignments.show', [$module, $assignment]), route('practicums.materials.show', $material), route('practicums.materials.download', $material), route('practicums.submissions.download', $submission)] as $url) {
        $this->get($url)->assertRedirect(route('login'));
    }
});

test('a lecturer creates an owned class and cannot assign ownership through unexpected input', function () {
    $lecturer = User::factory()->dosen()->create();
    $otherLecturer = User::factory()->dosen()->create();

    Livewire::actingAs($lecturer)->test('pages::practicums.index')->set('form', [...practicumModuleInput($otherLecturer), 'student_id' => 999, 'final_score' => 100])->call('save')->assertHasNoErrors()->assertSee('Praktikum Manufaktur');

    $this->assertDatabaseHas('practicum_modules', ['code' => 'TM-301-A-2026', 'lecturer_id' => $lecturer->id, 'semester' => 3, 'published' => true]);
});

test('admin can assign a lecturer while students cannot create classes', function () {
    $lecturer = User::factory()->dosen()->create();
    $admin = User::factory()->admin()->create();
    Livewire::actingAs($admin)->test('pages::practicums.index')->set('form', practicumModuleInput($lecturer))->call('save')->assertHasNoErrors();
    $this->assertDatabaseHas('practicum_modules', ['lecturer_id' => $lecturer->id]);

    Livewire::actingAs(User::factory()->create())->test('pages::practicums.index')->set('form', practicumModuleInput($lecturer))->call('save')->assertForbidden();
    $this->assertDatabaseCount('practicum_modules', 1);
});

test('module authorization distinguishes ownership enrolment publication and role', function (UserRole $role, bool $owns, bool $enrolled, bool $published, bool $view, bool $manage) {
    $user = User::factory()->create(['role' => $role]);
    $module = PracticumModule::factory()->create(['lecturer_id' => $owns ? $user->id : User::factory()->dosen(), 'published' => $published]);
    if ($enrolled) {
        PracticumEnrollment::factory()->for($module, 'module')->for($user, 'student')->create();
    }

    expect(Gate::forUser($user)->allows('view', $module))->toBe($view);
    expect(Gate::forUser($user)->allows('manage', $module))->toBe($manage);
})->with([
    'admin' => [UserRole::Admin, false, false, false, true, true],
    'own lecturer' => [UserRole::Dosen, true, false, false, true, true],
    'foreign lecturer' => [UserRole::Dosen, false, false, true, false, false],
    'enrolled student' => [UserRole::Mahasiswa, false, true, true, true, false],
    'draft class' => [UserRole::Mahasiswa, false, true, false, false, false],
    'unassigned student' => [UserRole::Mahasiswa, false, false, true, false, false],
    'industry mentor' => [UserRole::MentorDudi, false, false, true, false, false],
]);

test('lecturers cannot modify or enrol students into another lecturers class', function () {
    $module = PracticumModule::factory()->create();
    $foreignLecturer = User::factory()->dosen()->create();

    $this->actingAs($foreignLecturer)->get(route('practicums.show', $module))->assertForbidden();
    Livewire::actingAs($foreignLecturer)->test('pages::practicums.index')->call('editModule', $module->id)->assertNotFound();
    $this->assertDatabaseCount('practicum_enrollments', 0);
});

test('lecturer enrolment is idempotent and withdrawing a student removes class access', function () {
    $module = PracticumModule::factory()->create();
    $student = User::factory()->create();
    $component = Livewire::actingAs($module->lecturer)->test('pages::practicums.show', ['module' => $module])->set('studentId', (string) $student->id)->call('enrollStudent')->assertHasNoErrors();
    $component->set('studentId', (string) $student->id)->call('enrollStudent')->assertHasNoErrors();
    $this->assertDatabaseCount('practicum_enrollments', 1);
    $this->actingAs($student)->get(route('practicums.show', $module))->assertOk()->assertSee($module->title);

    $enrollment = PracticumEnrollment::sole();
    Livewire::actingAs($module->lecturer)->test('pages::practicums.show', ['module' => $module])->call('changeEnrollment', $enrollment->id, 'withdrawn')->assertHasNoErrors();
    $this->actingAs($student)->get(route('practicums.show', $module))->assertForbidden();
    $this->assertDatabaseHas('practicum_enrollments', ['id' => $enrollment->id, 'status' => 'withdrawn']);
});

test('students cannot enrol themselves and only student accounts can be added', function () {
    $enrollment = PracticumEnrollment::factory()->create();
    $module = $enrollment->module;
    Livewire::actingAs($enrollment->student)->test('pages::practicums.show', ['module' => $module])->set('studentId', (string) $enrollment->student_id)->call('enrollStudent')->assertForbidden();

    Livewire::actingAs($module->lecturer)->test('pages::practicums.show', ['module' => $module])->set('studentId', (string) $module->lecturer_id)->call('enrollStudent')->assertHasErrors(['student_id']);
    $this->assertDatabaseCount('practicum_enrollments', 1);
});

test('student catalogue filters semester program and skill without exposing other classes', function () {
    $student = User::factory()->create();
    $visible = PracticumModule::factory()->create(['title' => 'Manufaktur Presisi', 'study_program' => 'Teknik Mesin', 'semester' => 3, 'skill_area' => 'CNC']);
    $other = PracticumModule::factory()->create(['title' => 'Jaringan Lanjut', 'study_program' => 'Teknik Informatika', 'semester' => 5, 'skill_area' => 'Jaringan']);
    PracticumModule::factory()->create(['title' => 'Kelas Rahasia Tidak Diikuti']);
    foreach ([$visible, $other] as $module) {
        PracticumEnrollment::factory()->for($module, 'module')->for($student, 'student')->create();
    }

    Livewire::actingAs($student)->test('pages::practicums.index')->assertSee('Manufaktur Presisi')->assertSee('Jaringan Lanjut')->assertDontSee('Kelas Rahasia Tidak Diikuti')
        ->set('semester', '3')->assertSee('Manufaktur Presisi')->assertDontSee('Jaringan Lanjut')
        ->set('semester', '')->set('studyProgram', 'Teknik Informatika')->assertSee('Jaringan Lanjut')->assertDontSee('Manufaktur Presisi')
        ->set('studyProgram', '')->set('skillArea', 'CNC')->assertSee('Manufaktur Presisi')->assertDontSee('Jaringan Lanjut');
});

test('class validation rejects invalid dates semester and lecturer role', function (string $field, mixed $value) {
    $admin = User::factory()->admin()->create();
    $lecturer = User::factory()->dosen()->create();

    Livewire::actingAs($admin)->test('pages::practicums.index')->set('form', practicumModuleInput($lecturer))->set('form.'.$field, $value)->call('save')->assertHasErrors([$field]);
    $this->assertDatabaseCount('practicum_modules', 0);
})->with(['semester' => ['semester', 0], 'reverse dates' => ['ends_on', '2026-09-30'], 'timezone' => ['timezone', 'UTC'], 'missing title' => ['title', ''], 'lecturer' => ['lecturer_id', 99999]]);

test('practicum content is escaped and an assignment cannot be bound under another class', function () {
    $module = PracticumModule::factory()->create(['description' => '<script>alert("class")</script>']);
    $other = PracticumModule::factory()->for($module->lecturer, 'lecturer')->create();
    $assignment = PracticumAssignment::factory()->for($other, 'module')->create();

    $this->actingAs($module->lecturer)->get(route('practicums.show', $module))->assertOk()->assertSee('<script>alert("class")</script>')->assertDontSee('<script>alert("class")</script>', false);
    $this->get(route('practicums.assignments.show', [$module, $assignment]))->assertNotFound();
});
