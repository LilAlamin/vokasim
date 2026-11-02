<?php

use App\Actions\ReviewInternshipLeave;
use App\Actions\SubmitInternshipLeave;
use App\Models\InternshipAttendance;
use App\Models\InternshipLeaveRequest;
use App\Models\InternshipPlacement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function leaveInput(array $changes = []): array
{
    return [...['kind' => 'leave', 'starts_on' => '2026-10-31', 'ends_on' => '2026-10-31', 'reason' => 'Mengikuti kegiatan akademik wajib kampus.'], ...$changes];
}

test('students submit sick leave with private evidence and cannot forge approval', function () {
    Storage::fake('local');
    $this->travelTo(CarbonImmutable::parse('2026-10-31 08:00', 'Asia/Jakarta'));
    $placement = InternshipPlacement::factory()->create();

    Livewire::actingAs($placement->student)->test('pages::internships.leaves')->set('placementId', (string) $placement->id)
        ->set('form', leaveInput(['kind' => 'sick', 'status' => 'approved', 'reviewer_id' => $placement->student_id]))
        ->set('evidence', UploadedFile::fake()->create('doctor.pdf', 10, 'application/pdf'))->call('submit')->assertHasNoErrors();
    $leave = InternshipLeaveRequest::query()->sole();

    $this->assertDatabaseHas('internship_leave_requests', ['id' => $leave->id, 'internship_placement_id' => $placement->id, 'status' => 'pending', 'reviewer_id' => null]);
    Storage::disk('local')->assertExists($leave->evidence_path);
    expect($leave->toArray())->not->toHaveKey('evidence_path');
    $this->actingAs($placement->student)->get(route('internships.leaves.evidence', $leave))->assertDownload();
    $this->actingAs($placement->mentor)->get(route('internships.leaves.evidence', $leave))->assertDownload();
    $this->actingAs($placement->lecturer)->get(route('internships.leaves.evidence', $leave))->assertNotFound();
    $this->actingAs(User::factory()->create())->get(route('internships.leaves.evidence', $leave))->assertNotFound();
});

test('leave submission validates evidence dates and required reasons', function (array $changes, string $error) {
    $this->travelTo(CarbonImmutable::parse('2026-10-31 08:00', 'Asia/Jakarta'));
    $placement = InternshipPlacement::factory()->create(['starts_on' => '2026-10-01', 'ends_on' => '2026-12-31']);

    Livewire::actingAs($placement->student)->test('pages::internships.leaves')->set('form', leaveInput($changes))->call('submit')->assertHasErrors([$error]);

    $this->assertDatabaseCount('internship_leave_requests', 0);
})->with([
    'missing sick evidence' => [['kind' => 'sick'], 'evidence'],
    'before placement' => [['starts_on' => '2026-09-30'], 'starts_on'],
    'after placement' => [['ends_on' => '2027-01-01'], 'ends_on'],
    'reversed dates' => [['ends_on' => '2026-10-30'], 'ends_on'],
    'long leave' => [['ends_on' => '2026-12-15'], 'ends_on'],
    'empty reason' => [['reason' => ''], 'reason'],
]);

test('students cannot request leave for another placement or view its reasons', function () {
    $this->freezeTime();
    $placement = InternshipPlacement::factory()->create();
    InternshipLeaveRequest::factory()->for($placement, 'placement')->create(['reason' => 'Private leave reason from another placement']);
    $other = User::factory()->create();

    Livewire::actingAs($other)->test('pages::internships.leaves')->assertDontSee('Private leave reason from another placement')
        ->set('placementId', (string) $placement->id)->set('form', leaveInput())->call('submit')->assertNotFound();

    $this->assertDatabaseCount('internship_leave_requests', 1);
});

test('unsafe or oversized leave evidence is rejected before permanent storage', function (string $name, int $kilobytes, string $mime) {
    Storage::fake('local');
    $this->travelTo(CarbonImmutable::parse('2026-10-31 08:00', 'Asia/Jakarta'));
    $placement = InternshipPlacement::factory()->create();

    Livewire::actingAs($placement->student)->test('pages::internships.leaves')
        ->set('form', leaveInput(['kind' => 'sick']))
        ->set('evidence', UploadedFile::fake()->create($name, $kilobytes, $mime))
        ->call('submit')->assertHasErrors(['evidence']);

    $this->assertDatabaseCount('internship_leave_requests', 0);
    expect(Storage::disk('local')->allFiles('internship-leaves'))->toBe([]);
})->with([
    'active file type' => ['evidence.svg', 1, 'image/svg+xml'],
    'oversized PDF' => ['evidence.pdf', 5121, 'application/pdf'],
]);

test('only the assigned mentor can decide a leave request', function () {
    $leave = InternshipLeaveRequest::factory()->create();
    $other = User::factory()->mentorDudi()->create();

    Livewire::actingAs($other)->test('pages::internships.leaves')->call('selectLeave', $leave->id)->assertNotFound();
    Livewire::actingAs($leave->placement->student)->test('pages::internships.leaves')->call('selectLeave', $leave->id)->assertForbidden();
    Livewire::actingAs($leave->placement->mentor)->test('pages::internships.leaves')->call('selectLeave', $leave->id)
        ->set('feedback', 'Silakan mengikuti kegiatan kampus.')->call('review', 'approved')->assertHasNoErrors();

    $this->assertDatabaseHas('internship_leave_requests', ['id' => $leave->id, 'status' => 'approved', 'reviewer_id' => $leave->placement->mentor_id]);
    expect($leave->fresh()->reviewed_at)->not->toBeNull();
});

test('rejection requires feedback and a decision cannot later be overwritten', function () {
    $leave = InternshipLeaveRequest::factory()->create();
    $page = Livewire::actingAs($leave->placement->mentor)->test('pages::internships.leaves')->call('selectLeave', $leave->id);

    $page->call('review', 'rejected')->assertHasErrors(['feedback'])->assertSee('Jelaskan alasan penolakan');
    $page->set('feedback', 'Tanggal pengajuan tidak sesuai jadwal izin.')->call('review', 'rejected')->assertHasNoErrors();

    expect(fn () => app(ReviewInternshipLeave::class)->handle($leave->placement->mentor, $leave->id, 'approved'))->toThrow(ValidationException::class, 'Pengajuan ini sudah diputuskan.');
    $this->assertDatabaseHas('internship_leave_requests', ['id' => $leave->id, 'status' => 'rejected', 'feedback' => 'Tanggal pengajuan tidak sesuai jadwal izin.']);
});

test('overlapping pending leave and recorded attendance reject full day leave', function (string $conflict) {
    $this->travelTo(CarbonImmutable::parse('2026-10-31 08:00', 'Asia/Jakarta'));
    $placement = InternshipPlacement::factory()->create();
    if ($conflict === 'leave') {
        InternshipLeaveRequest::factory()->for($placement, 'placement')->create();
    } else {
        InternshipAttendance::factory()->for($placement, 'placement')->create();
    }

    expect(fn () => app(SubmitInternshipLeave::class)->handle($placement->student, $placement->id, leaveInput()))->toThrow(ValidationException::class);

    $this->assertDatabaseCount('internship_leave_requests', $conflict === 'leave' ? 1 : 0);
})->with(['leave', 'attendance']);

test('approved leave prevents check in and newly recorded attendance prevents approval', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-31 08:00', 'Asia/Jakarta'));
    $placement = InternshipPlacement::factory()->create();
    InternshipLeaveRequest::factory()->for($placement, 'placement')->approved()->create();

    Livewire::actingAs($placement->student)->test('pages::internships.logbook')
        ->call('recordAttendance', 'in', ['latitude' => -6.2, 'longitude' => 106.8166667, 'accuracy' => 5])->assertHasErrors(['attendance']);
    $this->assertDatabaseCount('internship_attendances', 0);

    $second = InternshipPlacement::factory()->create();
    $leave = InternshipLeaveRequest::factory()->for($second, 'placement')->create();
    InternshipAttendance::factory()->for($second, 'placement')->create();

    expect(fn () => app(ReviewInternshipLeave::class)->handle($second->mentor, $leave->id, 'approved'))->toThrow(ValidationException::class, 'Presensi sudah tercatat');
    $this->assertDatabaseHas('internship_leave_requests', ['id' => $leave->id, 'status' => 'pending']);
});
