<?php

use App\Actions\NotifyVokasimActivity;
use App\Actions\NotifyVokasimUser;
use App\Enums\CertificateStatus;
use App\Enums\InterviewStatus;
use App\Enums\LogbookStatus;
use App\Models\Certificate;
use App\Models\CertificateReview;
use App\Models\IndustryEvaluation;
use App\Models\InternshipLeaveRequest;
use App\Models\InternshipLogbook;
use App\Models\InternshipPlacement;
use App\Models\InterviewNote;
use App\Models\InterviewSession;
use App\Models\LogbookReview;
use App\Models\PracticumAssessment;
use App\Models\PracticumEnrollment;
use App\Models\PracticumModule;
use App\Models\PracticumSubmission;
use App\Models\User;
use App\Notifications\VokasimNotification;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

test('a notification event is saved once per recipient and email is disabled by default', function () {
    Notification::fake();
    $student = User::factory()->create();
    $other = User::factory()->create();
    $notify = app(NotifyVokasimUser::class);

    $created = $notify->handle($student, 'event-1', 'certificate', 'Status sertifikat', 'Ada pembaruan pengajuan Anda.', 'certificates.index');
    $duplicate = $notify->handle($student, 'event-1', 'certificate', 'Status sertifikat', 'Ada pembaruan pengajuan Anda.', 'certificates.index');
    $secondRecipient = $notify->handle($other, 'event-1', 'certificate', 'Status sertifikat', 'Ada pembaruan pengajuan Anda.', 'certificates.index');

    expect($created)->toBeTrue();
    expect($duplicate)->toBeFalse();
    expect($secondRecipient)->toBeTrue();
    $this->assertDatabaseCount('notifications', 2);
    expect($student->unreadNotifications()->count())->toBe(1);
    expect(array_keys($student->notifications()->sole()->data))->toBe(['category', 'title', 'message', 'route', 'parameters']);
    Notification::assertNothingSent();
});

test('optional email is queued once after commit and inactive users receive no notification', function () {
    config(['notifications.email_enabled' => true]);
    Queue::fake([SendQueuedNotifications::class]);
    $student = User::factory()->create();
    $inactive = User::factory()->inactive()->create();
    $notify = app(NotifyVokasimUser::class);

    $notify->handle($student, 'mail-event', 'interview', 'Evaluasi siap', 'Buka sesi untuk membaca hasil.', 'interviews.index');
    $notify->handle($student, 'mail-event', 'interview', 'Evaluasi siap', 'Buka sesi untuk membaca hasil.', 'interviews.index');
    $notify->handle($inactive, 'mail-event', 'interview', 'Evaluasi siap', 'Buka sesi untuk membaca hasil.', 'interviews.index');

    Queue::assertPushed(SendQueuedNotifications::class, fn (SendQueuedNotifications $job): bool => $job->notification instanceof VokasimNotification && $job->channels === ['mail']);
    Queue::assertPushed(SendQueuedNotifications::class, 1);
    expect($inactive->notifications()->count())->toBe(0);
    expect($student->notifications()->count())->toBe(1);
});

test('activity observers wait for committed changes and omit private interview content', function () {
    $student = User::factory()->create();
    $session = InterviewSession::factory()->for($student, 'student')->active()->create();

    DB::transaction(function () use ($session, $student): void {
        $session->update(['status' => InterviewStatus::Completed, 'overall_score' => 91.75, 'evaluation' => ['summary' => 'PRIVATE INTERVIEW ANSWER']]);
        expect($student->notifications()->count())->toBe(0);
    });

    $notice = $student->notifications()->sole();
    expect($notice->data)->toMatchArray(['category' => 'interview', 'route' => 'interviews.session', 'parameters' => ['session' => $session->public_id]]);
    expect(json_encode($notice->data))->not->toContain('PRIVATE INTERVIEW ANSWER')->not->toContain('91.75');
    app(NotifyVokasimActivity::class)->handle($session);
    expect($student->notifications()->count())->toBe(1);
});

test('rolled back activity does not leave a notification', function () {
    $student = User::factory()->create();
    $session = InterviewSession::factory()->for($student, 'student')->active()->create();

    expect(fn () => DB::transaction(function () use ($session, $student): void {
        $session->update(['status' => InterviewStatus::Failed, 'error_message' => 'PRIVATE PROVIDER FAILURE']);
        expect($student->notifications()->count())->toBe(0);
        throw new RuntimeException('rollback test');
    }))->toThrow(RuntimeException::class, 'rollback test');

    expect($student->notifications()->count())->toBe(0);
    expect($session->fresh()->status)->toBe(InterviewStatus::Active);
});

test('logbook notifications follow the mentor lecturer and student review stages', function () {
    $placement = InternshipPlacement::factory()->create();
    $otherLecturer = User::factory()->dosen()->create();
    $logbook = InternshipLogbook::factory()->for($placement, 'placement')->pending()->create();

    LogbookReview::factory()->for($logbook, 'logbook')->create();
    LogbookReview::factory()->for($logbook, 'logbook')->create(['reviewer_id' => $placement->mentor_id, 'from_status' => LogbookStatus::PendingMentor, 'to_status' => LogbookStatus::MentorApproved, 'feedback' => 'PRIVATE MENTOR NOTE']);
    LogbookReview::factory()->for($logbook, 'logbook')->create(['reviewer_id' => $placement->lecturer_id, 'from_status' => LogbookStatus::MentorApproved, 'to_status' => LogbookStatus::Verified]);

    expect($placement->mentor->notifications()->count())->toBe(1);
    expect($placement->lecturer->notifications()->count())->toBe(1);
    expect($placement->student->notifications()->count())->toBe(2);
    expect($otherLecturer->notifications()->count())->toBe(0);
    expect($placement->lecturer->notifications()->sole()->data)->toMatchArray(['route' => 'internships.reviews']);
    expect(json_encode($placement->student->notifications()->get()->pluck('data')->all()))->not->toContain('PRIVATE MENTOR NOTE');
});

test('certificate submissions notify only assigned lecturers and administrators then notify the owner of decisions', function () {
    $placement = InternshipPlacement::factory()->create();
    $module = PracticumModule::factory()->create();
    PracticumEnrollment::factory()->for($module, 'module')->for($placement->student, 'student')->create();
    $administrator = User::factory()->admin()->create();
    $otherLecturer = User::factory()->dosen()->create();
    $certificate = Certificate::factory()->for($placement->student, 'student')->create();

    $submitted = CertificateReview::factory()->for($certificate)->create(['reviewer_id' => $placement->student_id, 'from_status' => null, 'to_status' => CertificateStatus::Pending]);
    app(NotifyVokasimActivity::class)->handle($submitted);

    expect($placement->lecturer->notifications()->count())->toBe(1);
    expect($module->lecturer->notifications()->count())->toBe(1);
    expect($administrator->notifications()->count())->toBe(1);
    expect($otherLecturer->notifications()->count())->toBe(0);
    expect($placement->mentor->notifications()->count())->toBe(0);
    expect($placement->student->notifications()->count())->toBe(0);

    CertificateReview::factory()->for($certificate)->create(['reviewer_id' => $placement->lecturer_id, 'to_status' => CertificateStatus::Rejected, 'feedback' => 'PRIVATE CERTIFICATE NOTE']);

    $notice = $placement->student->notifications()->sole();
    expect($notice->data)->toMatchArray(['category' => 'certificate', 'route' => 'certificates.index', 'parameters' => ['certificate' => $certificate->id]]);
    expect(json_encode($notice->data))->not->toContain('PRIVATE CERTIFICATE NOTE')->not->toContain($certificate->credential_number);
});

test('leave decisions and evaluation revisions notify only the linked participants', function () {
    $placement = InternshipPlacement::factory()->create();
    $leave = InternshipLeaveRequest::factory()->for($placement, 'placement')->create(['reason' => 'PRIVATE MEDICAL REASON']);

    $leave->update(['status' => 'approved', 'reviewer_id' => $placement->mentor_id, 'feedback' => 'PRIVATE LEAVE NOTE']);
    $evaluation = IndustryEvaluation::factory()->for($placement, 'placement')->create(['final_score' => 77.35, 'feedback' => 'PRIVATE EVALUATION NOTE']);
    $evaluation->update(['revision' => 2]);

    expect($placement->mentor->notifications()->count())->toBe(1);
    expect($placement->lecturer->notifications()->count())->toBe(2);
    expect($placement->student->notifications()->count())->toBe(3);
    expect(json_encode($placement->student->notifications()->get()->pluck('data')->all()))
        ->not->toContain('PRIVATE MEDICAL REASON')->not->toContain('PRIVATE LEAVE NOTE')->not->toContain('PRIVATE EVALUATION NOTE')->not->toContain('77.35');
});

test('practicum submission revision and assessment notifications contain links without grades or feedback', function () {
    $submission = PracticumSubmission::factory()->create();
    $student = $submission->student;
    $lecturer = $submission->assignment->module->lecturer;

    $submission->update(['status' => 'revision_requested', 'revision_feedback' => 'PRIVATE PRACTICUM FEEDBACK']);
    $assessment = PracticumAssessment::factory()->for($submission, 'submission')->create(['feedback' => 'PRIVATE RUBRIC FEEDBACK', 'final_score' => 88.65]);
    $submission->update(['status' => 'graded']);
    app(NotifyVokasimActivity::class)->handle($assessment->fresh());

    expect($lecturer->notifications()->count())->toBe(1);
    expect($student->notifications()->count())->toBe(2);
    expect(json_encode($student->notifications()->get()->pluck('data')->all()))
        ->not->toContain('PRIVATE PRACTICUM FEEDBACK')->not->toContain('PRIVATE RUBRIC FEEDBACK')->not->toContain('88.65');
    expect($student->notifications()->first()->data['route'])->toBe('practicums.assignments.show');
});

test('a private lecturer interview note alerts its student without revealing the note in metadata', function () {
    $session = InterviewSession::factory()->active()->create();

    InterviewNote::factory()->for($session, 'session')->create(['body' => 'PRIVATE INTERVIEW MENTOR NOTE']);

    $notice = $session->student->notifications()->sole();
    expect($notice->data)->toMatchArray(['category' => 'interview', 'route' => 'interviews.session', 'parameters' => ['session' => $session->public_id]]);
    expect(json_encode($notice->data))->not->toContain('PRIVATE INTERVIEW MENTOR NOTE');
});

test('notification listing and read actions cannot access another users notifications', function () {
    $student = User::factory()->create();
    $other = User::factory()->create();
    $notify = app(NotifyVokasimUser::class);
    $notify->handle($student, 'own', 'certificate', 'Pembaruan milik saya', 'Buka pengajuan.', 'certificates.index');
    $notify->handle($other, 'foreign', 'certificate', 'PRIVATE OTHER USER NOTIFICATION', 'Buka pengajuan.', 'certificates.index');
    $ownNotice = $student->notifications()->sole();
    $foreign = $other->notifications()->sole();

    $this->actingAs($student)->get(route('notifications.index'))->assertOk()->assertSee('Pembaruan milik saya')->assertDontSee('PRIVATE OTHER USER NOTIFICATION');
    Livewire::actingAs($student)->test('pages::notifications.index')->call('markRead', $foreign->id)->assertNotFound();
    Livewire::actingAs($student)->test('pages::notifications.index')->call('openNotification', $foreign->id)->assertNotFound();
    Livewire::actingAs($student)->test('pages::notifications.index')->call('markAllRead')->assertHasNoErrors()->assertSet('unreadCount', 0);

    $this->assertDatabaseHas('notifications', ['id' => $foreign->id, 'read_at' => null]);
    expect($ownNotice->fresh()->read_at)->not->toBeNull();
});

test('unread and category filters are restored from the URL and opening a notice marks only it read', function () {
    $student = User::factory()->create();
    $notify = app(NotifyVokasimUser::class);
    $notify->handle($student, 'cert', 'certificate', 'Sertifikat yang belum dibaca', 'Detail pengajuan.', 'certificates.index');
    $notice = $student->notifications()->sole();
    $notify->handle($student, 'interview', 'interview', 'Hasil wawancara', 'Detail sesi.', 'interviews.index');

    Livewire::actingAs($student)->withQueryParams(['filter' => 'unread', 'category' => 'certificate'])->test('pages::notifications.index')
        ->assertSee('Sertifikat yang belum dibaca')->assertDontSee('Hasil wawancara')
        ->call('openNotification', $notice->id)->assertRedirectToRoute('certificates.index');

    expect($notice->fresh()->read_at)->not->toBeNull();
    expect($student->unreadNotifications()->count())->toBe(1);
});

test('notification text is escaped and the inbox requires a verified account', function () {
    $this->get(route('notifications.index'))->assertRedirectToRoute('login');
    $this->actingAs(User::factory()->unverified()->create())->get(route('notifications.index'))->assertRedirectToRoute('verification.notice');
    $student = User::factory()->create();
    $script = '<script>alert("notice")</script>';
    app(NotifyVokasimUser::class)->handle($student, 'escaping', 'practicum', $script, $script, 'dashboard');

    $this->actingAs($student)->get(route('notifications.index'))->assertOk()->assertSee($script)->assertDontSeeHtml($script);
});
