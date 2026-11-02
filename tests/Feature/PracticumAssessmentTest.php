<?php

use App\Actions\AssessPracticumSubmission;
use App\Actions\SavePracticumAssignment;
use App\Models\PracticumAssessment;
use App\Models\PracticumAssignment;
use App\Models\PracticumAttendance;
use App\Models\PracticumEnrollment;
use App\Models\PracticumModule;
use App\Models\PracticumSession;
use App\Models\PracticumSubmission;
use App\Models\User;
use Database\Seeders\PracticumSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function practicumAssignmentInput(): array
{
    return ['title' => 'Laporan CNC', 'instructions' => 'Dokumentasikan parameter mesin, hasil pengukuran, dan evaluasi penerapan K3.', 'due_at' => '2026-11-05T17:00', 'weight' => 1, 'allow_late' => false, 'criteria' => [
        ['title' => 'Akurasi komponen', 'category' => 'hard_skill', 'max_score' => 4, 'weight' => 60],
        ['title' => 'K3 dan kerja sama', 'category' => 'soft_skill', 'max_score' => 5, 'weight' => 40],
    ]];
}

function practicumReportUpload(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('laporan.txt', 'Hasil pengukuran komponen dan evaluasi prosedur keselamatan praktik.');
}

test('reports preserve revision history and lecturer grades normalize weighted rubric scores', function () {
    Storage::fake('local');
    config(['livewire.temporary_file_upload.cleanup' => false]);
    $this->travelTo(now('UTC')->setDate(2026, 10, 31)->setTime(2, 0));
    $enrollment = PracticumEnrollment::factory()->create();
    $module = $enrollment->module;
    Livewire::actingAs($module->lecturer)->test('pages::practicums.show', ['module' => $module])->call('selectTab', 'assignments')->set('assignmentForm', practicumAssignmentInput())->call('saveAssignment')->assertHasNoErrors()->assertSee('Laporan CNC');
    $assignment = PracticumAssignment::sole();
    $criteria = $assignment->rubricCriteria;
    Livewire::actingAs($enrollment->student)->test('pages::practicums.assignment', ['module' => $module, 'assignment' => $assignment])->set('reportFile', practicumReportUpload())->set('notes', 'Hasil pengukuran awal')->call('submitReport')->assertHasNoErrors()->assertSee('Menunggu penilaian');
    $first = PracticumSubmission::sole();
    Storage::disk('local')->assertExists($first->file_path);
    Livewire::actingAs($module->lecturer)->test('pages::practicums.assignment', ['module' => $module, 'assignment' => $assignment])->call('selectSubmission', $first->id)->set('revisionFeedback', 'Lengkapi analisis toleransi dan hasil pengukuran.')->call('requestRevision')->assertHasNoErrors()->assertSee('Perlu revisi');
    $this->assertDatabaseHas('practicum_submissions', ['id' => $first->id, 'status' => 'revision_requested', 'reviewer_id' => $module->lecturer_id]);

    $this->travelTo(now('UTC')->setDate(2026, 11, 6));
    Livewire::actingAs($enrollment->student)->test('pages::practicums.assignment', ['module' => $module, 'assignment' => $assignment])->assertSee('Lengkapi analisis toleransi')->set('reportFile', practicumReportUpload())->set('notes', 'Analisis toleransi telah dilengkapi')->call('submitReport')->assertHasNoErrors();
    $latest = PracticumSubmission::query()->latestAttempts()->sole();
    expect($latest->attempt)->toBe(2);
    expect($latest->is_late)->toBeTrue();
    Storage::disk('local')->assertExists([$first->file_path, $latest->file_path]);
    Livewire::actingAs($module->lecturer)->test('pages::practicums.assignment', ['module' => $module, 'assignment' => $assignment])->call('selectSubmission', $latest->id)
        ->set('reviewForm', ['scores' => [$criteria[0]->id => 3, $criteria[1]->id => 4], 'feedback' => 'Analisis sudah tepat; tingkatkan ketelitian finishing.'])->call('assess')->assertHasNoErrors()->assertSee('77.00');

    $this->assertDatabaseHas('practicum_assessments', ['practicum_submission_id' => $latest->id, 'final_score' => 77, 'hard_skill_score' => 75, 'soft_skill_score' => 80, 'assessor_id' => $module->lecturer_id]);
    $this->assertDatabaseHas('practicum_submissions', ['id' => $latest->id, 'status' => 'graded']);
    $this->assertDatabaseCount('practicum_submissions', 2);
    Livewire::actingAs($enrollment->student)->test('pages::practicums.assignment', ['module' => $module, 'assignment' => $assignment])->assertSee('77.00')->assertSee('Riwayat revisi')->assertSee('Analisis sudah tepat');
    expect($enrollment->progress())->toMatchArray(['assignment_count' => 1, 'submitted_count' => 1, 'graded_count' => 1, 'completion_percentage' => 100, 'average_grade' => 77.0]);
});

test('rubrics require both competency types and weights totaling one hundred', function (array $criteria) {
    $this->travelTo(now('UTC')->setDate(2026, 10, 31));
    $module = PracticumModule::factory()->create();
    $form = [...practicumAssignmentInput(), 'criteria' => $criteria];

    Livewire::actingAs($module->lecturer)->test('pages::practicums.show', ['module' => $module])->set('assignmentForm', $form)->call('saveAssignment')->assertHasErrors(['criteria'])->assertSee('Rubrik harus memuat hard skill dan soft skill');
    $this->assertDatabaseCount('practicum_assignments', 0);
})->with([
    'wrong total' => [[['title' => 'Teknis', 'category' => 'hard_skill', 'max_score' => 100, 'weight' => 60], ['title' => 'Komunikasi', 'category' => 'soft_skill', 'max_score' => 100, 'weight' => 20]]],
    'missing soft skill' => [[['title' => 'Teknis', 'category' => 'hard_skill', 'max_score' => 100, 'weight' => 60], ['title' => 'Hasil', 'category' => 'hard_skill', 'max_score' => 100, 'weight' => 40]]],
]);

test('rubric changes are blocked after submission while due dates remain editable', function () {
    $this->travelTo(now('UTC')->setDate(2026, 10, 31));
    $module = PracticumModule::factory()->create();
    $assignment = (new SavePracticumAssignment)->handle($module->lecturer, $module->id, practicumAssignmentInput());
    PracticumSubmission::factory()->for($assignment, 'assignment')->create();
    $component = Livewire::actingAs($module->lecturer)->test('pages::practicums.show', ['module' => $module])->call('editAssignment', $assignment->id)->set('assignmentForm.criteria.0.max_score', 10)->call('saveAssignment')->assertHasErrors(['criteria']);
    expect($assignment->rubricCriteria()->first()->max_score)->toBe(4.0);

    $component->call('editAssignment', $assignment->id)->set('assignmentForm.due_at', '2026-11-10T17:00')->call('saveAssignment')->assertHasNoErrors();
    expect($assignment->fresh()->due_at->format('Y-m-d H:i'))->toBe('2026-11-10 10:00');
});

test('students cannot replace submitted or graded reports without a revision request', function (string $status) {
    Storage::fake('local');
    $this->freezeTime();
    $submission = PracticumSubmission::factory()->create(['status' => $status]);
    $assignment = $submission->assignment;

    Livewire::actingAs($submission->student)->test('pages::practicums.assignment', ['module' => $assignment->module, 'assignment' => $assignment])->set('reportFile', practicumReportUpload())->call('submitReport')->assertHasErrors(['submission']);
    $this->assertDatabaseCount('practicum_submissions', 1);
    $this->assertDatabaseHas('practicum_submissions', ['id' => $submission->id, 'status' => $status, 'file_path' => $submission->file_path]);
})->with(['submitted', 'graded']);

test('late first reports are rejected unless the lecturer explicitly permits them', function () {
    Storage::fake('local');
    $this->freezeTime();
    $enrollment = PracticumEnrollment::factory()->create();
    $assignment = PracticumAssignment::factory()->for($enrollment->module, 'module')->create(['due_at' => now('UTC')->subDay()]);
    $component = Livewire::actingAs($enrollment->student)->test('pages::practicums.assignment', ['module' => $enrollment->module, 'assignment' => $assignment])->set('reportFile', practicumReportUpload())->call('submitReport')->assertHasErrors(['submission']);
    $this->assertDatabaseCount('practicum_submissions', 0);
    $assignment->update(['allow_late' => true]);

    $component->call('submitReport')->assertHasNoErrors();
    $this->assertDatabaseHas('practicum_submissions', ['student_id' => $enrollment->student_id, 'is_late' => true]);
});

test('reports require a valid private file and cannot open before the class period', function () {
    Storage::fake('local');
    $this->freezeTime();
    $enrollment = PracticumEnrollment::factory()->create();
    $assignment = PracticumAssignment::factory()->for($enrollment->module, 'module')->create();
    $component = Livewire::actingAs($enrollment->student)->test('pages::practicums.assignment', ['module' => $enrollment->module, 'assignment' => $assignment])->call('submitReport')->assertHasErrors(['file']);
    $component->set('reportFile', UploadedFile::fake()->createWithContent('unsafe.html', '<script>alert(1)</script>'))->call('submitReport')->assertHasErrors(['file']);
    $enrollment->module->update(['starts_on' => now()->addDay()->toDateString()]);

    $component->set('reportFile', practicumReportUpload())->call('submitReport')->assertHasErrors(['submission']);
    $this->assertDatabaseCount('practicum_submissions', 0);
});

test('students cannot assign grades or request revisions even when enrolled in the class', function () {
    $submission = PracticumSubmission::factory()->create();
    $assignment = $submission->assignment;
    Livewire::actingAs($submission->student)->test('pages::practicums.assignment', ['module' => $assignment->module, 'assignment' => $assignment])->set('reviewForm', ['scores' => [1 => 100], 'feedback' => 'Unapproved grade'])->call('assess')->assertForbidden();
    Livewire::actingAs($submission->student)->test('pages::practicums.assignment', ['module' => $assignment->module, 'assignment' => $assignment])->call('requestRevision')->assertForbidden();

    $this->assertDatabaseCount('practicum_assessments', 0);
    $this->assertDatabaseHas('practicum_submissions', ['id' => $submission->id, 'status' => 'submitted']);
});

test('foreign lecturer cannot view reports or assign grades in another lecturers class', function () {
    $submission = PracticumSubmission::factory()->create();
    $lecturer = User::factory()->dosen()->create();

    $this->actingAs($lecturer)->get(route('practicums.assignments.show', [$submission->assignment->module, $submission->assignment]))->assertForbidden();
    $this->get(route('practicums.submissions.download', $submission))->assertForbidden();
    $this->assertDatabaseCount('practicum_assessments', 0);
});

test('students cannot see classmates reports and private downloads enforce the report owner', function () {
    Storage::fake('local');
    $submission = PracticumSubmission::factory()->create(['notes' => 'Catatan pribadi hasil proyek mahasiswa satu']);
    $other = PracticumEnrollment::factory()->for($submission->assignment->module, 'module')->create();
    Storage::disk('local')->put($submission->file_path, 'private report');

    $this->actingAs($other->student)->get(route('practicums.assignments.show', [$submission->assignment->module, $submission->assignment]))->assertOk()->assertDontSee('Catatan pribadi hasil proyek mahasiswa satu');
    $this->get(route('practicums.submissions.download', $submission))->assertForbidden();
    $this->actingAs($submission->student)->get(route('practicums.submissions.download', $submission))->assertDownload('laporan.pdf')->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->actingAs($submission->assignment->module->lecturer)->get(route('practicums.submissions.download', $submission))->assertDownload('laporan.pdf');
});

test('scores must cover every criterion within range and cannot include foreign criterion IDs', function (string $case) {
    $submission = PracticumSubmission::factory()->create();
    $criteria = $submission->assignment->rubricCriteria;
    $scores = [$criteria[0]->id => 80, $criteria[1]->id => 70];
    $field = 'scores.'.$criteria[0]->id;
    if ($case === 'missing') {
        unset($scores[$criteria[0]->id]);
    } elseif ($case === 'above maximum') {
        $scores[$criteria[0]->id] = 101;
    } elseif ($case === 'negative') {
        $scores[$criteria[0]->id] = -1;
    } else {
        $scores[99999] = 100;
        $field = 'scores';
    }

    Livewire::actingAs($submission->assignment->module->lecturer)->test('pages::practicums.assignment', ['module' => $submission->assignment->module, 'assignment' => $submission->assignment])->call('selectSubmission', $submission->id)->set('reviewForm', ['scores' => $scores, 'feedback' => 'Catatan penilaian dosen'])->call('assess')->assertHasErrors([$field]);
    $this->assertDatabaseCount('practicum_assessments', 0);
    $this->assertDatabaseHas('practicum_submissions', ['id' => $submission->id, 'status' => 'submitted']);
})->with(['missing', 'above maximum', 'negative', 'foreign criterion']);

test('old report versions cannot be graded after a revision has been submitted', function () {
    $old = PracticumSubmission::factory()->revisionRequested()->create();
    $latest = PracticumSubmission::factory()->for($old->assignment, 'assignment')->for($old->student, 'student')->create(['attempt' => 2]);
    $lecturer = $old->assignment->module->lecturer;

    expect(fn () => (new AssessPracticumSubmission)->handle($lecturer, $old->id, ['feedback' => 'This is stale', 'scores' => []]))->toThrow(ValidationException::class, 'Laporan ini memiliki versi lebih baru.');
    $this->assertDatabaseCount('practicum_assessments', 0);
    $this->assertDatabaseHas('practicum_submissions', ['id' => $latest->id, 'status' => 'submitted']);
});

test('revision requests require feedback and cannot reopen an already graded report', function () {
    $submission = PracticumSubmission::factory()->create();
    $component = Livewire::actingAs($submission->assignment->module->lecturer)->test('pages::practicums.assignment', ['module' => $submission->assignment->module, 'assignment' => $submission->assignment])->call('selectSubmission', $submission->id)->call('requestRevision')->assertHasErrors(['revision_feedback']);
    $submission->update(['status' => 'graded']);

    $component->set('revisionFeedback', 'Please repeat this work')->call('requestRevision')->assertHasErrors(['submission']);
    $this->assertDatabaseHas('practicum_submissions', ['id' => $submission->id, 'status' => 'graded', 'revision_feedback' => null]);
});

test('class progress weights assignments correctly and ignores prior revisions and future sessions', function () {
    $this->freezeTime();
    $enrollment = PracticumEnrollment::factory()->create();
    $module = $enrollment->module;
    $firstAssignment = PracticumAssignment::factory()->for($module, 'module')->create(['weight' => 1]);
    $secondAssignment = PracticumAssignment::factory()->for($module, 'module')->create(['weight' => 3]);
    PracticumAssignment::factory()->for($module, 'module')->create(['weight' => 9]);
    $old = PracticumSubmission::factory()->for($firstAssignment, 'assignment')->for($enrollment->student, 'student')->revisionRequested()->create();
    $first = PracticumSubmission::factory()->for($firstAssignment, 'assignment')->for($enrollment->student, 'student')->graded()->create(['attempt' => 2]);
    $second = PracticumSubmission::factory()->for($secondAssignment, 'assignment')->for($enrollment->student, 'student')->graded()->create();
    PracticumAssessment::factory()->for($first, 'submission')->create(['final_score' => 77]);
    PracticumAssessment::factory()->for($second, 'submission')->create(['final_score' => 90]);
    $session = PracticumSession::factory()->for($module, 'module')->completed()->create();
    PracticumSession::factory()->for($module, 'module')->completed()->create();
    PracticumSession::factory()->for($module, 'module')->upcoming()->create();
    PracticumSession::factory()->for($module, 'module')->completed()->create(['cancelled' => true]);
    PracticumAttendance::factory()->for($session, 'session')->for($enrollment->student, 'student')->create(['status' => 'late']);

    expect($enrollment->progress())->toMatchArray(['assignment_count' => 3, 'submitted_count' => 2, 'graded_count' => 2, 'completion_percentage' => 67, 'average_grade' => 86.75, 'session_count' => 2, 'attended_count' => 1, 'attendance_percentage' => 50]);
    Livewire::actingAs($module->lecturer)->test('pages::practicums.show', ['module' => $module])->call('selectTab', 'progress')->assertSee('86.75')->assertSee($enrollment->student->name);
});

test('practicum demo seeding creates a complete repeatable learning workflow', function () {
    Storage::fake('local');
    $this->freezeTime();
    $this->seed(PracticumSeeder::class);
    $this->seed(PracticumSeeder::class);

    $this->assertDatabaseCount('practicum_modules', 1);
    $this->assertDatabaseCount('practicum_materials', 2);
    $this->assertDatabaseCount('practicum_sessions', 3);
    $this->assertDatabaseCount('practicum_assessments', 1);
    $submission = PracticumSubmission::sole();
    Storage::disk('local')->assertExists($submission->file_path);
    expect(PracticumEnrollment::sole()->progress())->toMatchArray(['average_grade' => 80.0, 'graded_count' => 1]);
});
