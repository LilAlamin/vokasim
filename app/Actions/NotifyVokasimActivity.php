<?php

namespace App\Actions;

use App\Enums\CertificateStatus;
use App\Enums\InterviewStatus;
use App\Enums\LogbookStatus;
use App\Enums\NotificationCategory;
use App\Enums\UserRole;
use App\Models\Certificate;
use App\Models\CertificateReview;
use App\Models\IndustryEvaluation;
use App\Models\InternshipLeaveRequest;
use App\Models\InternshipPlacement;
use App\Models\InterviewNote;
use App\Models\InterviewSession;
use App\Models\LogbookReview;
use App\Models\PracticumAssessment;
use App\Models\PracticumModule;
use App\Models\PracticumSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class NotifyVokasimActivity
{
    public function __construct(private NotifyVokasimUser $notify) {}

    public function handle(Model $record): void
    {
        if ($record instanceof LogbookReview) {
            $this->logbook($record);
        } elseif ($record instanceof CertificateReview) {
            $this->certificate($record);
        } elseif ($record instanceof InternshipLeaveRequest) {
            $this->leave($record);
        } elseif ($record instanceof IndustryEvaluation) {
            $this->evaluation($record);
        } elseif ($record instanceof PracticumSubmission) {
            $this->submission($record);
        } elseif ($record instanceof PracticumAssessment) {
            $this->assessment($record);
        } elseif ($record instanceof InterviewSession) {
            $this->interview($record);
        } elseif ($record instanceof InterviewNote) {
            $record->loadMissing('session.student');
            $this->send($record->session->student, 'interview-note:'.$record->id, NotificationCategory::Interview,
                'Catatan pembimbing tersedia', 'Dosen pembimbing menambahkan catatan pada sesi '.$record->session->target_position.'.',
                'interviews.session', ['session' => $record->session->public_id]);
        }
    }

    private function logbook(LogbookReview $review): void
    {
        $review->loadMissing(['logbook.placement.student', 'logbook.placement.mentor', 'logbook.placement.lecturer']);
        $placement = $review->logbook->placement;
        $key = 'logbook-review:'.$review->id;
        $date = $review->logbook->work_date->format('d M Y');

        if ($review->to_status === LogbookStatus::PendingMentor && $placement->mentor->isMentorDudi()) {
            $this->send($placement->mentor, $key, NotificationCategory::Internship, 'Logbook menunggu validasi',
                $placement->student->name.' mengirim logbook tanggal '.$date.'.', 'internships.reviews');
        } elseif ($review->to_status === LogbookStatus::MentorApproved) {
            if ($placement->lecturer->isDosen()) {
                $this->send($placement->lecturer, $key, NotificationCategory::Internship, 'Logbook siap diverifikasi',
                    'Mentor menyetujui logbook '.$placement->student->name.' tanggal '.$date.'.', 'internships.reviews');
            }

            $this->send($placement->student, $key, NotificationCategory::Internship, 'Logbook disetujui mentor',
                'Logbook tanggal '.$date.' diteruskan kepada dosen pembimbing.', 'internships.logbook');
        } elseif (in_array($review->to_status, [LogbookStatus::Verified, LogbookStatus::Draft], true)) {
            $this->send($placement->student, $key, NotificationCategory::Internship,
                $review->to_status === LogbookStatus::Verified ? 'Logbook terverifikasi' : 'Logbook perlu diperbaiki',
                'Buka logbook tanggal '.$date.' untuk melihat keputusan dan catatan pembimbing.', 'internships.logbook');
        }
    }

    private function certificate(CertificateReview $review): void
    {
        $review->loadMissing('certificate.student');
        $certificate = $review->certificate;
        $key = 'certificate-review:'.$review->id;

        if ($review->to_status === CertificateStatus::Pending) {
            foreach ($this->certificateReviewers($certificate) as $reviewer) {
                $this->send($reviewer, $key, NotificationCategory::Certificate, 'Sertifikat menunggu verifikasi',
                    $certificate->student->name.' mengajukan '.$certificate->title.'.', 'certificates.index', ['certificate' => $certificate->id]);
            }

            return;
        }

        $this->send($certificate->student, $key, NotificationCategory::Certificate, 'Status sertifikat diperbarui',
            $certificate->title.': '.$review->to_status->label().'. Buka pengajuan untuk melihat rinciannya.',
            'certificates.index', ['certificate' => $certificate->id]);
    }

    /** @return Collection<int, User> */
    private function certificateReviewers(Certificate $certificate): Collection
    {
        return User::query()->where('id', '!=', $certificate->student_id)->where(function (Builder $users) use ($certificate): void {
            $users->where('role', UserRole::Admin)->orWhere(function (Builder $lecturers) use ($certificate): void {
                $lecturers->where('role', UserRole::Dosen)->where(function (Builder $assigned) use ($certificate): void {
                    $assigned->whereIn('id', InternshipPlacement::query()->where('student_id', $certificate->student_id)->select('lecturer_id'))
                        ->orWhereIn('id', PracticumModule::query()->select('lecturer_id')->whereHas('enrollments',
                            fn (Builder $enrollments) => $enrollments->where('student_id', $certificate->student_id)->where('status', 'active')));
                });
            });
        })->get();
    }

    private function leave(InternshipLeaveRequest $leave): void
    {
        $leave->loadMissing(['placement.student', 'placement.mentor']);
        $key = 'leave:'.$leave->id.':'.$leave->status;

        if ($leave->status === 'pending' && $leave->placement->mentor->isMentorDudi()) {
            $this->send($leave->placement->mentor, $key, NotificationCategory::Internship, 'Pengajuan izin magang',
                $leave->placement->student->name.' mengajukan izin mulai '.$leave->starts_on->format('d M Y').'.', 'internships.leaves');
        } elseif (in_array($leave->status, ['approved', 'rejected'], true)) {
            $this->send($leave->placement->student, $key, NotificationCategory::Internship, 'Keputusan izin magang tersedia',
                'Pengajuan izin mulai '.$leave->starts_on->format('d M Y').': '.$leave->statusLabel().'.', 'internships.leaves');
        }
    }

    private function evaluation(IndustryEvaluation $evaluation): void
    {
        $evaluation->loadMissing(['placement.student', 'placement.lecturer']);
        $key = 'industry-evaluation:'.$evaluation->id.':'.$evaluation->revision;
        $message = 'Evaluasi magang periode '.$evaluation->period_month->format('M Y').' sudah tersedia. Buka evaluasi untuk melihat penilaian dan saran pengembangan.';
        $this->send($evaluation->placement->student, $key, NotificationCategory::Internship, 'Evaluasi magang diperbarui', $message, 'internships.evaluations');

        if ($evaluation->placement->lecturer->isDosen()) {
            $this->send($evaluation->placement->lecturer, $key, NotificationCategory::Internship, 'Evaluasi mahasiswa bimbingan tersedia',
                'Mentor memperbarui evaluasi '.$evaluation->placement->student->name.' periode '.$evaluation->period_month->format('M Y').'.', 'internships.evaluations');
        }
    }

    private function submission(PracticumSubmission $submission): void
    {
        $submission->loadMissing(['assignment.module.lecturer', 'student']);
        $key = 'practicum-submission:'.$submission->id.':'.$submission->status;
        $parameters = ['module' => $submission->assignment->practicum_module_id, 'assignment' => $submission->practicum_assignment_id];

        if ($submission->status === 'submitted' && $submission->assignment->module->lecturer->isDosen()) {
            $this->send($submission->assignment->module->lecturer, $key, NotificationCategory::Practicum, 'Laporan praktikum menunggu penilaian',
                $submission->student->name.' mengumpulkan '.$submission->assignment->title.'.', 'practicums.assignments.show', $parameters);
        } elseif ($submission->status === 'revision_requested') {
            $this->send($submission->student, $key, NotificationCategory::Practicum, 'Laporan praktikum perlu diperbaiki',
                'Buka tugas '.$submission->assignment->title.' untuk melihat catatan dosen dan mengirim perbaikan.', 'practicums.assignments.show', $parameters);
        } elseif ($submission->status === 'graded') {
            $submission->loadMissing('assessment');

            if ($submission->assessment !== null) {
                $this->assessment($submission->assessment);
            }
        }
    }

    private function assessment(PracticumAssessment $assessment): void
    {
        $assessment->loadMissing(['submission.student', 'submission.assignment']);

        if ($assessment->submission->status !== 'graded') {
            return;
        }

        $key = 'practicum-assessment:'.$assessment->id.':'.hash('sha256', serialize([$assessment->scores, $assessment->feedback, $assessment->assessed_at->toISOString()]));
        $this->send($assessment->submission->student, $key, NotificationCategory::Practicum, 'Penilaian praktikum tersedia',
            'Dosen memperbarui penilaian '.$assessment->submission->assignment->title.'. Buka tugas untuk melihat rubrik dan umpan balik.',
            'practicums.assignments.show', ['module' => $assessment->submission->assignment->practicum_module_id, 'assignment' => $assessment->submission->practicum_assignment_id]);
    }

    private function interview(InterviewSession $session): void
    {
        if (! in_array($session->status, [InterviewStatus::Completed, InterviewStatus::Failed], true)) {
            return;
        }

        $session->loadMissing('student');
        $this->send($session->student, 'interview:'.$session->id.':'.$session->status->value.':'.$session->retry_count, NotificationCategory::Interview,
            $session->status === InterviewStatus::Completed ? 'Evaluasi wawancara siap dibaca' : 'Sesi wawancara perlu dicoba ulang',
            'Buka sesi '.$session->target_position.' untuk melihat pembaruan dan langkah berikutnya.', 'interviews.session', ['session' => $session->public_id]);
    }

    /** @param array<string, int|string> $parameters */
    private function send(User $recipient, string $key, NotificationCategory $category, string $title, string $message, string $route, array $parameters = []): void
    {
        $this->notify->handle($recipient, $key, $category->value, $title, $message, $route, $parameters);
    }
}
