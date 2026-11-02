<?php

namespace App\Http\Controllers;

use App\Actions\BuildTracerAnalytics;
use App\Enums\CertificateStatus;
use App\Enums\InterviewStatus;
use App\Enums\LogbookStatus;
use App\Enums\UserRole;
use App\Models\Certificate;
use App\Models\InternshipAttendance;
use App\Models\InternshipLeaveRequest;
use App\Models\InternshipLogbook;
use App\Models\InternshipPlacement;
use App\Models\InternshipProject;
use App\Models\InterviewSession;
use App\Models\PartnerAgreement;
use App\Models\PartnerCompany;
use App\Models\PortfolioProject;
use App\Models\PracticumAssessment;
use App\Models\PracticumAttendance;
use App\Models\PracticumEnrollment;
use App\Models\PracticumModule;
use App\Models\PracticumSession;
use App\Models\PracticumSubmission;
use App\Models\TracerResponse;
use App\Models\User;
use App\Services\CareerRecommendations;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use stdClass;

class DashboardController extends Controller
{
    private const array INTERNSHIP_TIMEZONES = ['Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura'];

    public function __invoke(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('dashboard', [
            ...$this->internshipData($user),
            ...match ($user->role) {
                UserRole::Mahasiswa => $this->studentData($user),
                UserRole::Dosen => $this->lecturerData($user),
                UserRole::MentorDudi => $this->mentorData($user),
                UserRole::Admin => $this->adminData($user),
            },
        ]);
    }

    /** @return array<string, mixed> */
    private function internshipData(User $user): array
    {
        $logbooks = InternshipLogbook::query()->forUser($user);
        $pendingStatus = $user->isMentorDudi() ? LogbookStatus::PendingMentor : LogbookStatus::MentorApproved;
        $pending = (clone $logbooks)->where('status', $pendingStatus);
        $latest = $user->isMahasiswa() ? clone $logbooks : clone $pending;
        $activePlacements = $this->activePlacements($user);
        $activePlacement = $user->isMahasiswa()
            ? (clone $activePlacements)->with(['mentor:id,name', 'lecturer:id,name'])->orderBy('starts_on')->orderBy('id')->first()
            : null;
        $verifiedHours = $user->isMahasiswa()
            ? ($activePlacement?->verifiedHours() ?? 0)
            : (float) (clone $logbooks)->where('status', LogbookStatus::Verified)->sum('effective_minutes') / 60;

        $attendanceToday = InternshipAttendance::query()->whereHas('placement', fn (Builder $query) => $query->forUser($user))
            ->where(function (Builder $query): void {
                foreach (self::INTERNSHIP_TIMEZONES as $timezone) {
                    $query->orWhere(fn (Builder $site) => $site->whereDate('work_date', CarbonImmutable::now($timezone)->toDateString())
                        ->whereHas('placement', fn (Builder $placement) => $placement->where('timezone', $timezone)));
                }
            })->count();

        return [
            'internshipStudentCount' => (clone $activePlacements)->distinct()->count('student_id'),
            'internshipAttendanceToday' => $attendanceToday,
            'internshipPendingCount' => (clone $pending)->count(),
            'internshipVerifiedHours' => $verifiedHours,
            'internshipTargetHours' => $activePlacement->target_hours ?? 0,
            'internshipProgress' => $activePlacement && $activePlacement->target_hours > 0 ? min(100, $verifiedHours / $activePlacement->target_hours * 100) : 0,
            'internshipActivePlacement' => $activePlacement,
            'internshipRecentLogbooks' => $latest->with('placement.student:id,name')->orderByDesc('work_date')->orderByDesc('id')->limit(3)->get(),
        ];
    }

    /** @return Builder<InternshipPlacement> */
    private function activePlacements(User $user): Builder
    {
        return InternshipPlacement::query()->forUser($user)->where(function (Builder $query): void {
            foreach (self::INTERNSHIP_TIMEZONES as $timezone) {
                $today = CarbonImmutable::now($timezone)->toDateString();
                $query->orWhere(fn (Builder $site) => $site->where('timezone', $timezone)
                    ->whereDate('starts_on', '<=', $today)->whereDate('ends_on', '>=', $today));
            }
        });
    }

    /** @return array<string, mixed> */
    private function studentData(User $user): array
    {
        $enrollments = PracticumEnrollment::query()->forUser($user)->where('status', 'active')
            ->with([
                'module:id,title,code,timezone',
                'module.sessions' => fn ($query) => $query->where('cancelled', false)->where('starts_at', '<', now('UTC'))
                    ->select(['id', 'practicum_module_id', 'starts_at', 'cancelled']),
                'module.sessions.attendances' => fn ($query) => $query->where('student_id', $user->id)
                    ->select(['id', 'practicum_session_id', 'student_id', 'status']),
                'module.assignments:id,practicum_module_id,weight',
                'module.assignments.submissions' => fn ($query) => $query->whereIn('id', PracticumSubmission::query()->where('student_id', $user->id)->latestAttempts()->select('id'))
                    ->select(['id', 'practicum_assignment_id', 'student_id', 'attempt', 'status']),
                'module.assignments.submissions.assessment:id,practicum_submission_id,final_score,hard_skill_score,soft_skill_score',
            ])->orderByDesc('enrolled_at')->orderByDesc('id')->get();
        $modules = $enrollments->map(fn (PracticumEnrollment $enrollment): array => [
            'module' => $enrollment->module,
            'progress' => PracticumEnrollment::summarize($enrollment->module, $user->id),
        ]);
        $assignmentCount = (int) $modules->sum('progress.assignment_count');
        $gradedCount = (int) $modules->sum('progress.graded_count');
        $sessionCount = (int) $modules->sum('progress.session_count');
        $attendedCount = (int) $modules->sum('progress.attended_count');
        $careers = app(CareerRecommendations::class)->forStudent($user);

        return [
            'practicumSummary' => [
                'module_count' => $enrollments->count(),
                'assignment_count' => $assignmentCount,
                'graded_count' => $gradedCount,
                'completion_percentage' => $assignmentCount > 0 ? (int) round($gradedCount / $assignmentCount * 100) : null,
                'session_count' => $sessionCount,
                'attended_count' => $attendedCount,
                'attendance_percentage' => $sessionCount > 0 ? (int) round($attendedCount / $sessionCount * 100) : null,
            ],
            'practicumModules' => $modules->take(4),
            'upcomingSessions' => $this->upcomingSessions($user),
            'careerMatches' => array_slice(array_values(array_filter($careers['recommendations'], fn (array $career): bool => $career['match_percentage'] > 0)), 0, 2),
            'hasTracerResponse' => TracerResponse::query()->where('user_id', $user->id)->exists(),
            ...$this->portfolioData($user),
            ...$this->interviewData($user),
        ];
    }

    /** @return array<string, mixed> */
    private function lecturerData(User $user): array
    {
        $submissions = PracticumSubmission::query()->forUser($user)->latestAttempts()->where('status', 'submitted');
        $averageGrade = PracticumAssessment::query()->whereHas('submission', fn (Builder $query) => $query->forUser($user)
            ->latestAttempts()->where('status', 'graded'))->avg('final_score');

        return [
            'practicumModuleCount' => PracticumModule::query()->forUser($user)->count(),
            'practicumStudentCount' => PracticumEnrollment::query()->forUser($user)->where('status', 'active')->distinct()->count('student_id'),
            'practicumPendingCount' => (clone $submissions)->count(),
            'practicumAverageGrade' => $averageGrade === null ? null : round((float) $averageGrade, 1),
            'practicumAttendanceCount' => PracticumAttendance::query()->whereIn('status', ['present', 'late'])
                ->whereHas('session', fn (Builder $query) => $query->forUser($user)->where('cancelled', false)->where('starts_at', '<', now('UTC')))->count(),
            'practicumPendingSubmissions' => $submissions->with(['student:id,name', 'assignment:id,practicum_module_id,title', 'assignment.module:id,title'])
                ->orderBy('submitted_at')->orderBy('id')->limit(4)->get(['id', 'practicum_assignment_id', 'student_id', 'submitted_at', 'is_late']),
            'upcomingSessions' => $this->upcomingSessions($user),
            ...$this->portfolioData($user),
            ...$this->interviewData($user),
        ];
    }

    /** @return Collection<int, PracticumSession> */
    private function upcomingSessions(User $user): Collection
    {
        return PracticumSession::query()->forUser($user)->where('cancelled', false)->where('ends_at', '>=', now('UTC'))
            ->with('module:id,title,code,timezone')->orderBy('starts_at')->orderBy('id')->limit(4)
            ->get(['id', 'practicum_module_id', 'title', 'room', 'starts_at', 'ends_at', 'is_exam']);
    }

    /** @return array{portfolioProjectCount: int, verifiedCertificateCount: int, pendingCertificateCount: int} */
    private function portfolioData(User $user): array
    {
        $certificates = Certificate::query()->forUser($user);

        return [
            'portfolioProjectCount' => PortfolioProject::query()->forUser($user)->count(),
            'verifiedCertificateCount' => (clone $certificates)->where('status', CertificateStatus::Verified)->whereNotNull('verified_at')
                ->where(fn (Builder $query) => $query->whereNull('expires_on')->orWhereDate('expires_on', '>=', today()))->count(),
            'pendingCertificateCount' => (clone $certificates)->where('status', CertificateStatus::Pending)->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function interviewData(User $user): array
    {
        $sessions = InterviewSession::query()->forUser($user);
        $completed = (clone $sessions)->where('status', InterviewStatus::Completed);
        $averageScore = (clone $completed)->avg('overall_score');
        $latestScore = (clone $completed)->orderByDesc('completed_at')->orderByDesc('id')->value('overall_score');

        return [
            'interviewSummary' => [
                'completed_count' => (clone $completed)->count(),
                'average_score' => $averageScore === null ? null : round((float) $averageScore, 1),
                'latest_score' => $latestScore === null ? null : (float) $latestScore,
            ],
            'recentInterviews' => $sessions->with('student:id,name')->orderByDesc('created_at')->orderByDesc('id')->limit(3)
                ->get(['id', 'public_id', 'student_id', 'target_position', 'status', 'overall_score', 'star_score', 'technical_score', 'communication_score', 'created_at', 'completed_at']),
        ];
    }

    /** @return array<string, mixed> */
    private function mentorData(User $user): array
    {
        $leaves = InternshipLeaveRequest::query()->forUser($user)->where('status', 'pending');
        $monthlyDue = $this->activePlacements($user)->where(function (Builder $query): void {
            foreach (self::INTERNSHIP_TIMEZONES as $timezone) {
                $month = CarbonImmutable::now($timezone)->startOfMonth()->toDateString();
                $query->orWhere(fn (Builder $site) => $site->where('timezone', $timezone)
                    ->whereDoesntHave('evaluations', fn (Builder $evaluations) => $evaluations->where('period_type', 'monthly')->whereDate('period_month', $month)));
            }
        });
        $finalDue = InternshipPlacement::query()->forUser($user)->whereDoesntHave('evaluations', fn (Builder $query) => $query->where('period_type', 'final'))
            ->where(function (Builder $query): void {
                foreach (self::INTERNSHIP_TIMEZONES as $timezone) {
                    $query->orWhere(fn (Builder $site) => $site->where('timezone', $timezone)->whereDate('ends_on', '<=', CarbonImmutable::now($timezone)->toDateString()));
                }
            });

        return [
            'mentorPendingLeaveCount' => (clone $leaves)->count(),
            'mentorMonthlyEvaluationCount' => $monthlyDue->count(),
            'mentorFinalEvaluationCount' => $finalDue->count(),
            'mentorPendingLeaves' => $leaves->with('placement.student:id,name')->orderBy('starts_on')->orderBy('id')->limit(3)
                ->get(['id', 'internship_placement_id', 'kind', 'starts_on', 'ends_on']),
            'mentorInterns' => $this->activePlacements($user)->with('student:id,name')
                ->withSum(['logbooks as verified_minutes' => fn (Builder $query) => $query->where('status', LogbookStatus::Verified)], 'effective_minutes')
                ->orderBy('ends_on')->orderBy('id')->limit(4)->get(),
            'mentorProjects' => InternshipProject::query()->forUser($user)->whereColumn('completed_minutes', '<', 'target_minutes')
                ->with('placement.student:id,name')->orderBy('due_on')->orderBy('id')->limit(3)
                ->get(['id', 'internship_placement_id', 'title', 'due_on', 'target_minutes', 'completed_minutes']),
        ];
    }

    /** @return array<string, mixed> */
    private function adminData(User $user): array
    {
        $roles = User::query()->where('is_active', true)->selectRaw('role, COUNT(*) as user_count')->groupBy('role')->toBase()->get()
            ->mapWithKeys(fn (stdClass $group): array => [$group->role => (int) $group->user_count]);
        $studentCount = $roles->get(UserRole::Mahasiswa->value, 0);
        $lecturerCount = $roles->get(UserRole::Dosen->value, 0);
        $companies = PartnerCompany::query()->where('is_active', true);
        $completed = InterviewSession::query()->where('status', InterviewStatus::Completed);
        $averageScore = (clone $completed)->avg('overall_score');
        $programScores = (clone $completed)->join('users', 'users.id', '=', 'interview_sessions.student_id')
            ->selectRaw('users.study_program, COUNT(*) as session_count, COUNT(overall_score) as scored_count, AVG(overall_score) as overall_score, AVG(star_score) as star_score, AVG(technical_score) as technical_score, AVG(communication_score) as communication_score')
            ->groupBy('users.study_program')->orderByDesc('session_count')->orderBy('users.study_program')->limit(8)->toBase()->get()
            ->map(fn (stdClass $group): array => [
                'study_program' => $group->study_program ?: 'Program studi belum diisi',
                'session_count' => (int) $group->session_count,
                'scored_count' => (int) $group->scored_count,
                'overall_score' => $group->overall_score === null ? null : round((float) $group->overall_score, 1),
                'star_score' => $group->star_score === null ? null : round((float) $group->star_score, 1),
                'technical_score' => $group->technical_score === null ? null : round((float) $group->technical_score, 1),
                'communication_score' => $group->communication_score === null ? null : round((float) $group->communication_score, 1),
            ]);

        return [
            'adminSummary' => [
                'student_count' => $studentCount,
                'lecturer_count' => $lecturerCount,
                'mentor_count' => $roles->get(UserRole::MentorDudi->value, 0),
                'student_lecturer_ratio' => $lecturerCount > 0 ? round($studentCount / $lecturerCount, 1) : null,
                'practicum_module_count' => PracticumModule::query()->count(),
                'practicum_enrollment_count' => PracticumEnrollment::query()->where('status', 'active')->count(),
                'interview_completed_count' => (clone $completed)->count(),
                'interview_scored_count' => (clone $completed)->whereNotNull('overall_score')->count(),
                'interview_average_score' => $averageScore === null ? null : round((float) $averageScore, 1),
            ],
            'partnerSummary' => [
                'active_companies' => (clone $companies)->count(),
                'total_quota' => (int) (clone $companies)->sum('internship_quota'),
                'expiring_agreements' => PartnerAgreement::query()->expiringSoon()->count(),
            ],
            'expiringAgreements' => PartnerAgreement::query()->expiringSoon()->with('company:id,name')->orderBy('expires_on')->orderBy('id')->limit(3)
                ->get(['id', 'partner_company_id', 'reference_number', 'kind', 'expires_on']),
            'tracerSummary' => app(BuildTracerAnalytics::class)->handle($user),
            'interviewProgramScores' => $programScores,
            ...$this->portfolioData($user),
        ];
    }
}
