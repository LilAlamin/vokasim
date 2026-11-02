<?php

use App\Enums\LogbookStatus;
use App\Enums\UserRole;
use App\Models\InternshipAttendance;
use App\Models\InternshipLogbook;
use App\Models\InternshipPlacement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('a daily logbook moves from draft through mentor approval to lecturer verification', function () {
    Storage::fake('local');
    $this->travelTo(CarbonImmutable::parse('2026-10-31 17:00', 'Asia/Jakarta'));
    $placement = InternshipPlacement::factory()->create();
    InternshipAttendance::factory()->for($placement, 'placement')->completed()->create();

    $studentPage = Livewire::actingAs($placement->student)->test('pages::internships.logbook')
        ->set('description', 'Mengkalibrasi sensor suhu dan menguji sistem proteksi pada panel kontrol.')
        ->set('effectiveHours', '7.5')->set('photo', UploadedFile::fake()->image('kegiatan.jpg'))
        ->call('saveDraft')->assertHasNoErrors()->assertSee('Draft');
    $logbook = InternshipLogbook::sole();
    Storage::disk('local')->assertExists($logbook->photo_path);
    expect($logbook)->effective_minutes->toBe(450)->status->toBe(LogbookStatus::Draft);

    $studentPage->call('send')->assertHasNoErrors()->assertSee('Menunggu Mentor');
    $this->assertDatabaseCount('internship_logbooks', 1);
    $this->assertDatabaseHas('internship_logbooks', ['id' => $logbook->id, 'status' => 'pending_mentor']);

    Livewire::actingAs($placement->mentor)->test('pages::internships.reviews')
        ->assertSee($placement->student->name)->call('selectLogbook', $logbook->id)->assertSee('Dalam radius 100 m')
        ->set('feedback', 'Aktivitas dan jam kerja sesuai.')->call('review', 'approve')->assertHasNoErrors()->assertSee('Disetujui Mentor');
    expect($logbook->fresh()->mentor_approved_at)->not->toBeNull();
    expect($placement->verifiedHours())->toBe(0.0);

    Livewire::actingAs($placement->lecturer)->test('pages::internships.reviews')
        ->call('selectLogbook', $logbook->id)->call('review', 'approve')->assertHasNoErrors()->assertSee('Terverifikasi Dosen');
    expect($logbook->fresh()->verified_at)->not->toBeNull();
    expect($placement->verifiedHours())->toBe(7.5);
    $this->assertDatabaseCount('logbook_reviews', 3);
});

test('a draft can be saved incomplete and updated without duplicating the daily entry', function () {
    $this->freezeTime();
    $placement = InternshipPlacement::factory()->create();
    $page = Livewire::actingAs($placement->student)->test('pages::internships.logbook')->call('saveDraft')->assertHasNoErrors();
    $page->set('description', 'Memeriksa kondisi alat sebelum mulai bekerja.')->set('effectiveHours', '2.5')->call('saveDraft')->assertHasNoErrors();
    $this->assertDatabaseCount('internship_logbooks', 1);
    $this->assertDatabaseHas('internship_logbooks', ['status' => 'draft', 'effective_minutes' => 150]);
    $page->call('selectDay', now('Asia/Jakarta')->toDateString())->assertSet('effectiveHours', '2.5');
});

test('submitting requires a completed attendance', function () {
    $this->freezeTime();
    $placement = InternshipPlacement::factory()->create();
    Livewire::actingAs($placement->student)->test('pages::internships.logbook')->set('description', 'Melakukan pemeriksaan mesin dan mendokumentasikan kondisi komponen.')
        ->set('effectiveHours', '7')->call('send')->assertHasErrors(['logbook'])->assertSee('Lengkapi presensi datang dan pulang');
    $this->assertDatabaseHas('internship_logbooks', ['status' => 'draft']);
    $this->assertDatabaseCount('logbook_reviews', 0);
});

test('submission rejects missing evidence or hours exceeding actual presence', function (string $hours, string $description, string $error) {
    Storage::fake('local');
    $this->travelTo(CarbonImmutable::parse('2026-10-31 17:00', 'Asia/Jakarta'));
    $placement = InternshipPlacement::factory()->create();
    InternshipAttendance::factory()->for($placement, 'placement')->completed()->create();
    Livewire::actingAs($placement->student)->test('pages::internships.logbook')->set('description', $description)->set('effectiveHours', $hours)
        ->call('send')->assertHasErrors([$error]);
    $this->assertDatabaseHas('internship_logbooks', ['status' => 'draft']);
    $this->assertDatabaseCount('logbook_reviews', 0);
    Storage::disk('local')->assertDirectoryEmpty('internship-logbooks');
})->with([
    'too many hours' => ['8.25', 'Melakukan pemeriksaan mesin dan mendokumentasikan kondisi komponen.', 'effective_hours'],
    'zero hours' => ['0', 'Melakukan pemeriksaan mesin dan mendokumentasikan kondisi komponen.', 'effective_hours'],
    'incomplete description' => ['7', 'Tes', 'description'],
    'missing photograph' => ['7', 'Melakukan pemeriksaan mesin dan mendokumentasikan kondisi komponen.', 'photo'],
]);

test('future logbooks and dates outside the assignment are rejected', function (string $date) {
    $this->travelTo(CarbonImmutable::parse('2026-10-31 17:00', 'Asia/Jakarta'));
    $placement = InternshipPlacement::factory()->create(['starts_on' => '2026-10-01', 'ends_on' => '2026-12-31']);
    Livewire::actingAs($placement->student)->test('pages::internships.logbook')->set('workDate', $date)->call('saveDraft')->assertHasErrors(['work_date']);
    $this->assertDatabaseCount('internship_logbooks', 0);
})->with(['2026-11-01', '2026-09-30']);

test('uploads reject non images and oversized files', function (string $type) {
    Storage::fake('local');
    $this->freezeTime();
    $placement = InternshipPlacement::factory()->create();
    $file = $type === 'text' ? UploadedFile::fake()->create('unsafe.html', 1, 'text/html') : UploadedFile::fake()->image('large.jpg')->size(5121);
    Livewire::actingAs($placement->student)->test('pages::internships.logbook')->set('photo', $file)->call('saveDraft')->assertHasErrors(['photo']);
    $this->assertDatabaseCount('internship_logbooks', 0);
    Storage::disk('local')->assertDirectoryEmpty('internship-logbooks');
})->with(['text', 'large']);

test('replacing a draft photo removes the previous private file', function () {
    Storage::fake('local');
    $this->freezeTime();
    $placement = InternshipPlacement::factory()->create();
    $page = Livewire::actingAs($placement->student)->test('pages::internships.logbook')->set('photo', UploadedFile::fake()->image('first.jpg'))->call('saveDraft')->assertHasNoErrors();
    $original = InternshipLogbook::sole()->photo_path;
    $page->set('photo', UploadedFile::fake()->image('second.jpg'))->call('saveDraft')->assertHasNoErrors();
    Storage::disk('local')->assertMissing($original);
    Storage::disk('local')->assertExists(InternshipLogbook::sole()->photo_path);
});

test('a lecturer cannot bypass mentor approval', function () {
    $entry = InternshipLogbook::factory()->pending()->create();
    Livewire::actingAs($entry->placement->lecturer)->test('pages::internships.reviews')->call('selectLogbook', $entry->id)->call('review', 'approve')->assertForbidden();
    $this->assertDatabaseHas('internship_logbooks', ['id' => $entry->id, 'status' => 'pending_mentor']);
    $this->assertDatabaseCount('logbook_reviews', 0);
});

test('reviewing twice cannot approve the next stage', function () {
    $entry = InternshipLogbook::factory()->pending()->create();
    $page = Livewire::actingAs($entry->placement->mentor)->test('pages::internships.reviews')->call('selectLogbook', $entry->id);
    $page->call('review', 'approve')->assertHasNoErrors();
    $page->call('review', 'approve')->assertForbidden();
    $this->assertDatabaseHas('internship_logbooks', ['id' => $entry->id, 'status' => 'mentor_approved']);
    $this->assertDatabaseCount('logbook_reviews', 1);
});

test('lecturer revisions return to draft and require mentor approval again', function () {
    $this->freezeTime();
    $entry = InternshipLogbook::factory()->mentorApproved()->create();
    $page = Livewire::actingAs($entry->placement->lecturer)->test('pages::internships.reviews')->call('selectLogbook', $entry->id);
    $page->call('review', 'revise')->assertHasErrors(['feedback'])->assertSee('Tuliskan catatan perbaikan');
    $this->assertDatabaseCount('logbook_reviews', 0);
    $page->set('feedback', 'Jelaskan hasil pengujian dan kurangi jam istirahat.')->call('review', 'revise')->assertHasNoErrors();
    expect($entry->fresh())->status->toBe(LogbookStatus::Draft)->mentor_approved_at->toBeNull()->verified_at->toBeNull();
    Livewire::actingAs($entry->placement->student)->test('pages::internships.logbook')->assertSee('Jelaskan hasil pengujian')->assertSee('Dikembalikan untuk revisi');
    $this->assertDatabaseHas('logbook_reviews', ['from_status' => 'mentor_approved', 'to_status' => 'draft', 'reviewer_id' => $entry->placement->lecturer_id]);
});

test('submitted and verified logbooks cannot be edited by the student', function (LogbookStatus $status) {
    $this->freezeTime();
    $entry = InternshipLogbook::factory()->create(['status' => $status]);
    Livewire::actingAs($entry->placement->student)->test('pages::internships.logbook')->set('description', 'Changed after submission')->call('saveDraft')->assertForbidden();
    expect($entry->fresh()->description)->toBe($entry->description);
})->with([LogbookStatus::PendingMentor, LogbookStatus::MentorApproved, LogbookStatus::Verified]);

test('reviewers cannot see or act on another supervisors students', function (UserRole $role) {
    $entry = InternshipLogbook::factory()->pending()->create();
    $other = User::factory()->create(['role' => $role]);
    Livewire::actingAs($other)->test('pages::internships.reviews')->assertDontSee($entry->description)->assertDontSee($entry->placement->student->name)
        ->call('selectLogbook', $entry->id)->assertNotFound();
    $this->assertDatabaseCount('logbook_reviews', 0);
})->with([UserRole::MentorDudi, UserRole::Dosen]);

test('private photos are visible only to the student and assigned reviewers or admin', function () {
    Storage::fake('local');
    $file = UploadedFile::fake()->image('activity.jpg');
    $path = $file->store('internship-logbooks', 'local');
    $entry = InternshipLogbook::factory()->create(['photo_path' => $path]);
    $this->get(route('internships.photo', $entry))->assertRedirect(route('login'));
    foreach ([$entry->placement->student, $entry->placement->mentor, $entry->placement->lecturer, User::factory()->admin()->create()] as $user) {
        $this->actingAs($user)->get(route('internships.photo', $entry))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    }
    $this->actingAs(User::factory()->mentorDudi()->create())->get(route('internships.photo', $entry))->assertNotFound();
    Storage::disk('local')->assertExists($path);
});

test('review permissions require both assignment and the correct stage', function (LogbookStatus $status, string $actor, bool $allowed) {
    $entry = InternshipLogbook::factory()->create(['status' => $status]);
    $user = match ($actor) {
        'student' => $entry->placement->student,
        'mentor' => $entry->placement->mentor,
        'lecturer' => $entry->placement->lecturer,
        'admin' => User::factory()->admin()->create(),
        'other mentor' => User::factory()->mentorDudi()->create(),
    };
    expect(Gate::forUser($user)->allows('review', $entry))->toBe($allowed);
})->with([
    'mentor at pending' => [LogbookStatus::PendingMentor, 'mentor', true],
    'lecturer at approved' => [LogbookStatus::MentorApproved, 'lecturer', true],
    'mentor at draft' => [LogbookStatus::Draft, 'mentor', false],
    'lecturer at pending' => [LogbookStatus::PendingMentor, 'lecturer', false],
    'mentor at verified' => [LogbookStatus::Verified, 'mentor', false],
    'lecturer at verified' => [LogbookStatus::Verified, 'lecturer', false],
    'student' => [LogbookStatus::PendingMentor, 'student', false],
    'admin' => [LogbookStatus::PendingMentor, 'admin', false],
    'other mentor' => [LogbookStatus::PendingMentor, 'other mentor', false],
]);

test('students cannot open the reviewer page and unverified users must verify their email', function () {
    $this->actingAs(User::factory()->create())->get(route('internships.reviews'))->assertForbidden();
    $this->actingAs(User::factory()->unverified()->create())->get(route('internships.logbook'))->assertRedirect(route('verification.notice'));
});

test('review content is escaped and month filters include the final day', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-31 17:00', 'Asia/Jakarta'));
    $entry = InternshipLogbook::factory()->pending()->create(['description' => '<script>alert("xss")</script>']);
    Livewire::actingAs($entry->placement->mentor)->test('pages::internships.reviews')->set('month', '2026-10')->assertSee($entry->description)->assertDontSeeHtml($entry->description)
        ->call('selectLogbook', $entry->id)->assertDontSeeHtml($entry->description);
});
