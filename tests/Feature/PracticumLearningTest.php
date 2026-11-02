<?php

use App\Enums\UserRole;
use App\Models\PracticumAttendance;
use App\Models\PracticumEnrollment;
use App\Models\PracticumMaterial;
use App\Models\PracticumModule;
use App\Models\PracticumSession;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function practicumPdf(string $name = 'laporan.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF");
}

test('lecturer and student can render each relevant class section', function () {
    $enrollment = PracticumEnrollment::factory()->create();
    $module = $enrollment->module;
    $lecturer = Livewire::actingAs($module->lecturer)->test('pages::practicums.show', ['module' => $module]);
    foreach (['materials', 'assignments', 'progress', 'schedule'] as $tab) {
        $lecturer->call('selectTab', $tab)->assertOk();
    }
    $student = Livewire::actingAs($enrollment->student)->test('pages::practicums.show', ['module' => $module]);
    foreach (['materials', 'assignments', 'progress', 'schedule'] as $tab) {
        $student->call('selectTab', $tab)->assertOk();
    }
    $student->call('selectTab', 'progress')->assertSee('Capaian saya')->assertDontSee('Tambah peserta kelas');
});

test('session times are stored in UTC and overlapping schedules are rejected', function () {
    $this->travelTo(now('UTC')->setDate(2026, 10, 31)->setTime(1, 0));
    $module = PracticumModule::factory()->create(['timezone' => 'Asia/Makassar']);
    $form = ['title' => 'Uji mesin CNC', 'room' => 'Bengkel A', 'starts_at' => '2026-11-03T09:00', 'ends_at' => '2026-11-03T11:00', 'is_exam' => true, 'cancelled' => false];
    $component = Livewire::actingAs($module->lecturer)->test('pages::practicums.show', ['module' => $module])->set('sessionForm', $form)->call('saveSession')->assertHasNoErrors();

    $this->assertDatabaseHas('practicum_sessions', ['practicum_module_id' => $module->id, 'starts_at' => '2026-11-03 01:00:00', 'ends_at' => '2026-11-03 03:00:00', 'is_exam' => true]);
    $component->set('sessionForm', [...$form, 'title' => 'Jadwal tumpang tindih'])->call('saveSession')->assertHasErrors(['starts_at'])->assertSee('Jadwal bertabrakan dengan sesi lain di kelas ini.');
    $this->assertDatabaseCount('practicum_sessions', 1);
});

test('sessions cannot leave the class period or change after attendance exists', function () {
    $this->freezeTime();
    $session = PracticumSession::factory()->create();
    $form = ['title' => 'Sesi di luar periode', 'room' => 'Lab B', 'starts_at' => '2000-01-01T09:00', 'ends_at' => '2000-01-01T11:00', 'is_exam' => false, 'cancelled' => false];
    $component = Livewire::actingAs($session->module->lecturer)->test('pages::practicums.show', ['module' => $session->module])->set('sessionForm', $form)->call('saveSession')->assertHasErrors(['starts_at']);
    PracticumAttendance::factory()->for($session, 'session')->create();

    $component->call('editSession', $session->id)->set('sessionForm.cancelled', true)->call('saveSession')->assertHasErrors(['session']);
    $this->assertDatabaseHas('practicum_sessions', ['id' => $session->id, 'cancelled' => false]);
});

test('students check in only during their session and duplicate requests cannot overwrite attendance', function () {
    $this->freezeTime();
    $enrollment = PracticumEnrollment::factory()->create();
    $session = PracticumSession::factory()->for($enrollment->module, 'module')->create();
    $component = Livewire::actingAs($enrollment->student)->test('pages::practicums.show', ['module' => $enrollment->module])->call('checkIn', $session->id)->assertHasNoErrors()->assertSee('Hadir');

    $this->assertDatabaseHas('practicum_attendances', ['practicum_session_id' => $session->id, 'student_id' => $enrollment->student_id, 'recorded_by' => $enrollment->student_id, 'status' => 'present']);
    $component->call('checkIn', $session->id)->assertHasErrors(['attendance']);
    $this->assertDatabaseCount('practicum_attendances', 1);
});

test('late check ins are recorded as late without trusting a student supplied status', function () {
    $this->freezeTime();
    $enrollment = PracticumEnrollment::factory()->create();
    $session = PracticumSession::factory()->for($enrollment->module, 'module')->create(['starts_at' => now('UTC')->subMinutes(16)]);

    Livewire::actingAs($enrollment->student)->test('pages::practicums.show', ['module' => $enrollment->module])->set('attendanceStates', [$enrollment->student_id => 'present'])->call('checkIn', $session->id)->assertHasNoErrors();
    $this->assertDatabaseHas('practicum_attendances', ['status' => 'late', 'student_id' => $enrollment->student_id]);
});

test('students cannot check in to future ended or cancelled sessions', function (string $state) {
    $this->freezeTime();
    $enrollment = PracticumEnrollment::factory()->create();
    $attributes = match ($state) {
        'future' => ['starts_at' => now('UTC')->addHour(), 'ends_at' => now('UTC')->addHours(2)],
        'ended' => ['starts_at' => now('UTC')->subHours(2), 'ends_at' => now('UTC')->subHour()],
        'cancelled' => ['cancelled' => true],
    };
    $session = PracticumSession::factory()->for($enrollment->module, 'module')->create($attributes);

    Livewire::actingAs($enrollment->student)->test('pages::practicums.show', ['module' => $enrollment->module])->call('checkIn', $session->id)->assertHasErrors(['attendance']);
    $this->assertDatabaseCount('practicum_attendances', 0);
})->with(['future', 'ended', 'cancelled']);

test('students cannot record another class session or use lecturer attendance controls', function () {
    $this->freezeTime();
    $enrollment = PracticumEnrollment::factory()->create();
    $foreignSession = PracticumSession::factory()->create();
    $ownSession = PracticumSession::factory()->for($enrollment->module, 'module')->create();

    Livewire::actingAs($enrollment->student)->test('pages::practicums.show', ['module' => $enrollment->module])->call('checkIn', $foreignSession->id)->assertNotFound();
    Livewire::actingAs($enrollment->student)->test('pages::practicums.show', ['module' => $enrollment->module])->call('saveAttendance', $enrollment->student_id)->assertForbidden();
    $this->assertDatabaseCount('practicum_attendances', 0);
});

test('lecturers can correct attendance for enrolled students and reject unrelated accounts', function () {
    $this->freezeTime();
    $enrollment = PracticumEnrollment::factory()->create();
    $session = PracticumSession::factory()->for($enrollment->module, 'module')->completed()->create();
    $other = User::factory()->create();
    $component = Livewire::actingAs($enrollment->module->lecturer)->test('pages::practicums.show', ['module' => $enrollment->module])
        ->set('attendanceSessionId', (string) $session->id)->set('attendanceStates.'.$enrollment->student_id, 'excused')->set('attendanceNotes.'.$enrollment->student_id, 'Surat sakit diterima')->call('saveAttendance', $enrollment->student_id)->assertHasNoErrors();
    $this->assertDatabaseHas('practicum_attendances', ['student_id' => $enrollment->student_id, 'status' => 'excused', 'note' => 'Surat sakit diterima']);

    $component->set('attendanceStates.'.$other->id, 'present')->call('saveAttendance', $other->id)->assertHasErrors(['student_id']);
    $this->assertDatabaseCount('practicum_attendances', 1);
});

test('materials are uploaded privately and can be previewed and downloaded by class participants', function () {
    Storage::fake('local');
    $enrollment = PracticumEnrollment::factory()->create();
    $module = $enrollment->module;

    Livewire::actingAs($module->lecturer)->test('pages::practicums.show', ['module' => $module])->call('selectTab', 'materials')
        ->set('materialForm', ['title' => 'SOP penggunaan CNC', 'kind' => 'sop'])->set('materialFile', practicumPdf('sop-cnc.pdf'))->call('uploadMaterial')->assertHasNoErrors()->assertSee('SOP penggunaan CNC');
    $material = PracticumMaterial::sole();
    Storage::disk('local')->assertExists($material->file_path);
    $this->actingAs($enrollment->student)->get(route('practicums.materials.show', $material))->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->get(route('practicums.materials.download', $material))->assertDownload('sop-cnc.pdf');
    Livewire::actingAs($enrollment->student)->test('pages::practicums.show', ['module' => $module])->call('selectTab', 'materials')->call('previewMaterial', $material->id)->assertSee('Pembaca materi');
});

test('private materials reject unrelated roles and withdrawn enrolments', function (UserRole $role) {
    Storage::fake('local');
    $material = PracticumMaterial::factory()->create();
    Storage::disk('local')->put($material->file_path, '%PDF-1.4');
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)->get(route('practicums.materials.show', $material))->assertForbidden();
    $this->get(route('practicums.materials.download', $material))->assertForbidden();
    Storage::disk('local')->assertExists($material->file_path);
})->with([UserRole::Mahasiswa, UserRole::Dosen, UserRole::MentorDudi]);

test('withdrawing enrolment revokes existing private file links', function () {
    Storage::fake('local');
    $enrollment = PracticumEnrollment::factory()->withdrawn()->create();
    $material = PracticumMaterial::factory()->for($enrollment->module, 'module')->create();
    Storage::disk('local')->put($material->file_path, '%PDF-1.4');

    $this->actingAs($enrollment->student)->get(route('practicums.materials.download', $material))->assertForbidden();
    Storage::disk('local')->assertExists($material->file_path);
});

test('material uploads reject executable files and missing content without creating records', function () {
    Storage::fake('local');
    $module = PracticumModule::factory()->create();
    $component = Livewire::actingAs($module->lecturer)->test('pages::practicums.show', ['module' => $module])->set('materialForm', ['title' => 'Materi tidak aman', 'kind' => 'material']);
    $component->call('uploadMaterial')->assertHasErrors(['file']);
    $component->set('materialFile', UploadedFile::fake()->createWithContent('script.php', '<?php echo 1;'))->call('uploadMaterial')->assertHasErrors(['file']);

    $this->assertDatabaseCount('practicum_materials', 0);
    expect(Storage::disk('local')->files('practicum-materials'))->toBeEmpty();
});

test('only managers can remove a material and deletion removes its private file', function () {
    Storage::fake('local');
    $enrollment = PracticumEnrollment::factory()->create();
    $material = PracticumMaterial::factory()->for($enrollment->module, 'module')->create();
    Storage::disk('local')->put($material->file_path, '%PDF-1.4');
    Livewire::actingAs($enrollment->student)->test('pages::practicums.show', ['module' => $enrollment->module])->call('removeMaterial', $material->id)->assertForbidden();
    Storage::disk('local')->assertExists($material->file_path);

    Livewire::actingAs($enrollment->module->lecturer)->test('pages::practicums.show', ['module' => $enrollment->module])->call('removeMaterial', $material->id)->assertHasNoErrors();
    Storage::disk('local')->assertMissing($material->file_path);
    $this->assertDatabaseCount('practicum_materials', 0);
});
