<?php

use App\Enums\UserRole;
use App\Models\Certificate;
use App\Models\IndustryEvaluation;
use App\Models\InternshipAttendance;
use App\Models\InternshipLeaveRequest;
use App\Models\InternshipLogbook;
use App\Models\InternshipPlacement;
use App\Models\InternshipProject;
use App\Models\InterviewQuestion;
use App\Models\InterviewSession;
use App\Models\PartnerAgreement;
use App\Models\PartnerCompany;
use App\Models\PortfolioProject;
use App\Models\PracticumAssessment;
use App\Models\PracticumAssignment;
use App\Models\PracticumAttendance;
use App\Models\PracticumEnrollment;
use App\Models\PracticumModule;
use App\Models\PracticumSession;
use App\Models\PracticumSubmission;
use App\Models\TracerResponse;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('mahasiswa sees mahasiswa dashboard with AI mock interview banner', function () {
    $mahasiswa = User::factory()->create([
        'role' => UserRole::Mahasiswa,
    ]);

    $response = $this->actingAs($mahasiswa)->get(route('dashboard'));

    $response->assertOk()
        ->assertSee('Simulasi Wawancara HRD AI')
        ->assertSee('Kompetensi Praktik')
        ->assertSee('Jam Magang DUDI')
        ->assertViewHas('interviewSummary', ['completed_count' => 0, 'average_score' => null, 'latest_score' => null])
        ->assertViewHas('careerMatches', [])
        ->assertViewHas('practicumSummary', fn (array $summary): bool => $summary['completion_percentage'] === null && $summary['attendance_percentage'] === null);
});

test('dosen sees dosen dashboard with lab classes and review queue', function () {
    $dosen = User::factory()->dosen()->create();

    $response = $this->actingAs($dosen)->get(route('dashboard'));

    $response->assertOk()
        ->assertSee('Dosen / Instruktur')
        ->assertSee('Kelas Praktik Diampu')
        ->assertSee('Antrean Verifikasi Logbook Magang')
        ->assertViewHas('practicumAverageGrade', null);
});

test('mentor dudi sees mentor dashboard with intern validation', function () {
    $mentor = User::factory()->mentorDudi()->create();

    $response = $this->actingAs($mentor)->get(route('dashboard'));

    $response->assertOk()
        ->assertSee('Mentor Industri DUDI')
        ->assertSee('Presensi Geofencing GPS Aktif')
        ->assertSee('Validasi Aktivitas');
});

test('admin sees executive dashboard with tracer study and mou metrics', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk()
        ->assertSee('Executive Analytics Dashboard')
        ->assertSee('Keterserapan Lulusan di DUDI')
        ->assertSee('Kemitraan DUDI Aktif')
        ->assertViewHas('adminSummary', fn (array $summary): bool => $summary['interview_average_score'] === null && $summary['student_lecturer_ratio'] === null)
        ->assertViewHas('tracerSummary', fn (array $summary): bool => $summary['absorption_percentage'] === null && $summary['average_first_salary'] === null);
});

test('authenticated user can switch roles in local environment', function () {
    app()->instance('env', 'local');
    $user = User::factory()->create(['role' => UserRole::Mahasiswa]);

    $response = $this->actingAs($user)->withSession(['_token' => 'role-test-token'])->post(route('switch-role', 'dosen'), ['_token' => 'role-test-token']);

    $response->assertRedirect(route('dashboard'));
    expect($user->fresh()->role)->toBe(UserRole::Dosen);
});

test('role switching is disabled outside the local environment', function (string $environment) {
    app()->instance('env', $environment);
    $user = User::factory()->create();
    $this->actingAs($user)->withSession(['_token' => 'role-test-token'])->post(route('switch-role', 'admin'), ['_token' => 'role-test-token'])->assertNotFound();
    expect($user->fresh()->role)->toBe(UserRole::Mahasiswa);
})->with(['testing', 'production']);

test('role switching requires a post and a known role', function () {
    app()->instance('env', 'local');
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('switch-role', 'admin'))->assertMethodNotAllowed();
    $this->withSession(['_token' => 'role-test-token'])->post(route('switch-role', 'unknown'), ['_token' => 'role-test-token'])->assertNotFound();
    expect($user->fresh()->role)->toBe(UserRole::Mahasiswa);
});

test('dashboard internship summaries use verified scoped records', function () {
    $this->freezeTime();
    $placement = InternshipPlacement::factory()->create();
    InternshipLogbook::factory()->for($placement, 'placement')->verified()->create(['effective_minutes' => 150]);
    $pending = InternshipLogbook::factory()->for($placement, 'placement')->pending()->create(['work_date' => now()->subDay()->toDateString()]);
    InternshipLogbook::factory()->pending()->create(['description' => 'Private activity from another placement']);

    $this->actingAs($placement->student)->get(route('dashboard'))->assertOk()->assertViewHas('internshipVerifiedHours', 2.5)->assertDontSee('Private activity from another placement');
    $this->actingAs($placement->mentor)->get(route('dashboard'))->assertOk()->assertViewHas('internshipPendingCount', 1)->assertSee($pending->description)->assertDontSee('Private activity from another placement');
    $this->actingAs($placement->lecturer)->get(route('dashboard'))->assertOk()->assertViewHas('internshipPendingCount', 0);
});

test('role switching rejects requests without a csrf token', function () {
    app()->instance('env', 'local');
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('switch-role', 'admin'))->assertStatus(419);
    expect($user->fresh()->role)->toBe(UserRole::Mahasiswa);
});

test('student dashboard computes active class progress from latest attempts and eligible sessions', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-31T03:00:00Z'));
    $student = User::factory()->create(['gpa' => 3.78, 'semester' => 5]);
    $module = PracticumModule::factory()->create(['title' => '<script>alert("praktik")</script>']);
    PracticumEnrollment::factory()->for($student, 'student')->for($module, 'module')->create();
    $sessions = PracticumSession::factory()->for($module, 'module')->completed()->count(3)->create();
    PracticumAttendance::factory()->for($student, 'student')->for($sessions[0], 'session')->create();
    PracticumAttendance::factory()->for($student, 'student')->for($sessions[1], 'session')->create(['status' => 'late']);
    $cancelled = PracticumSession::factory()->for($module, 'module')->completed()->create(['cancelled' => true, 'title' => 'Cancelled practical session']);
    PracticumAttendance::factory()->for($student, 'student')->for($cancelled, 'session')->create();
    $exam = PracticumSession::factory()->for($module, 'module')->upcoming()->create(['is_exam' => true, 'title' => 'Ujian kompetensi terjadwal']);
    PracticumAttendance::factory()->for($student, 'student')->for($exam, 'session')->create();

    $firstAssignment = PracticumAssignment::factory()->for($module, 'module')->create(['weight' => 1]);
    $firstSubmission = PracticumSubmission::factory()->for($firstAssignment, 'assignment')->for($student, 'student')->graded()->create();
    PracticumAssessment::factory()->for($firstSubmission, 'submission')->create(['final_score' => 60, 'hard_skill_score' => 50, 'soft_skill_score' => 70]);
    $secondAssignment = PracticumAssignment::factory()->for($module, 'module')->create(['weight' => 3]);
    $secondSubmission = PracticumSubmission::factory()->for($secondAssignment, 'assignment')->for($student, 'student')->graded()->create();
    PracticumAssessment::factory()->for($secondSubmission, 'submission')->create(['final_score' => 100, 'hard_skill_score' => 90, 'soft_skill_score' => 100]);
    $revisedAssignment = PracticumAssignment::factory()->for($module, 'module')->create(['weight' => 5]);
    $oldSubmission = PracticumSubmission::factory()->for($revisedAssignment, 'assignment')->for($student, 'student')->graded()->create();
    PracticumAssessment::factory()->for($oldSubmission, 'submission')->create(['final_score' => 100]);
    PracticumSubmission::factory()->for($revisedAssignment, 'assignment')->for($student, 'student')->create(['attempt' => 2]);
    $anotherSubmission = PracticumSubmission::factory()->for($firstAssignment, 'assignment')->graded()->create();
    PracticumAssessment::factory()->for($anotherSubmission, 'submission')->create(['final_score' => 10]);

    $draft = PracticumModule::factory()->draft()->create(['title' => 'Unpublished private class']);
    PracticumEnrollment::factory()->for($student, 'student')->for($draft, 'module')->create();
    PracticumSession::factory()->for($draft, 'module')->upcoming()->create(['title' => 'Unpublished private exam']);
    $withdrawn = PracticumModule::factory()->create(['title' => 'Withdrawn class']);
    PracticumEnrollment::factory()->for($student, 'student')->for($withdrawn, 'module')->withdrawn()->create();
    PracticumAssignment::factory()->for($withdrawn, 'module')->create();

    $response = $this->actingAs($student)->get(route('dashboard'));

    $response->assertOk()->assertSee('3.78')->assertSee('Semester 5')->assertSee($exam->title)
        ->assertSee($module->title)->assertDontSee($module->title, false)
        ->assertDontSee('Unpublished private class')->assertDontSee('Unpublished private exam')->assertDontSee('Withdrawn class')->assertDontSee($cancelled->title)
        ->assertViewHas('practicumSummary', [
            'module_count' => 1, 'assignment_count' => 3, 'graded_count' => 2, 'completion_percentage' => 67,
            'session_count' => 3, 'attended_count' => 2, 'attendance_percentage' => 67,
        ]);
    expect($response->viewData('practicumModules')->first()['progress'])->toMatchArray([
        'average_grade' => 90.0, 'hard_skill_score' => 80.0, 'soft_skill_score' => 92.5, 'submitted_count' => 3,
    ]);
    expect($response->viewData('upcomingSessions')->modelKeys())->toBe([$exam->id]);
});

test('student dashboard uses own verified evidence and preserves a zero interview score', function () {
    $this->freezeTime();
    $student = User::factory()->create();
    $otherStudent = User::factory()->create();
    PortfolioProject::factory()->for($student, 'student')->count(2)->create();
    PortfolioProject::factory()->for($otherStudent, 'student')->create();
    Certificate::factory()->for($student, 'student')->verified()->create(['skills' => ['php'], 'expires_on' => today()->toDateString()]);
    Certificate::factory()->for($student, 'student')->expired()->create(['skills' => ['sql', 'laravel', 'git']]);
    Certificate::factory()->for($student, 'student')->pending()->create(['skills' => ['routing']]);
    Certificate::factory()->for($otherStudent, 'student')->verified()->create(['skills' => ['sql', 'laravel', 'git']]);
    InterviewSession::factory()->for($student, 'student')->completed()->create(['overall_score' => 90, 'created_at' => now()->subDays(2), 'completed_at' => now()->subDays(2)]);
    $lastCompleted = InterviewSession::factory()->for($student, 'student')->completed()->create(['overall_score' => 0, 'created_at' => now()->subDay(), 'completed_at' => now()->subDay()]);
    InterviewSession::factory()->for($student, 'student')->active()->create(['overall_score' => 100]);
    InterviewSession::factory()->for($otherStudent, 'student')->completed()->create(['target_position' => 'Private interview target', 'overall_score' => 100]);
    TracerResponse::factory()->for($otherStudent, 'user')->create();

    $response = $this->actingAs($student)->get(route('dashboard'));

    $response->assertOk()->assertViewHas('portfolioProjectCount', 2)->assertViewHas('verifiedCertificateCount', 1)
        ->assertViewHas('pendingCertificateCount', 1)->assertViewHas('hasTracerResponse', false)
        ->assertViewHas('interviewSummary', ['completed_count' => 2, 'average_score' => 45.0, 'latest_score' => 0.0])
        ->assertSee(route('interviews.session', $lastCompleted), false)->assertDontSee('Private interview target');
    expect($response->viewData('careerMatches'))->toHaveCount(1);
    expect($response->viewData('careerMatches')[0])->toMatchArray(['title' => 'Junior Web Developer', 'match_percentage' => 25, 'matched_skills' => ['php']]);
    expect($response->viewData('recentInterviews')->pluck('student_id')->unique()->all())->toBe([$student->id]);
});

test('lecturer dashboard scopes assessment queues schedules evidence and interviews to assigned students', function () {
    $this->freezeTime();
    $lecturer = User::factory()->dosen()->create();
    $student = User::factory()->create();
    $withdrawnStudent = User::factory()->create();
    $modules = PracticumModule::factory()->for($lecturer, 'lecturer')->count(2)->create();
    foreach ($modules as $module) {
        PracticumEnrollment::factory()->for($module, 'module')->for($student, 'student')->create();
    }
    PracticumEnrollment::factory()->for($modules[0], 'module')->for($withdrawnStudent, 'student')->withdrawn()->create();
    $gradedAssignment = PracticumAssignment::factory()->for($modules[0], 'module')->create();
    PracticumSubmission::factory()->for($gradedAssignment, 'assignment')->for($student, 'student')->create();
    $gradedSubmission = PracticumSubmission::factory()->for($gradedAssignment, 'assignment')->for($student, 'student')->graded()->create(['attempt' => 2]);
    PracticumAssessment::factory()->for($gradedSubmission, 'submission')->create(['final_score' => 50]);
    $secondAssignment = PracticumAssignment::factory()->for($modules[1], 'module')->create();
    $secondSubmission = PracticumSubmission::factory()->for($secondAssignment, 'assignment')->for($student, 'student')->graded()->create();
    PracticumAssessment::factory()->for($secondSubmission, 'submission')->create(['final_score' => 90]);
    $pendingAssignment = PracticumAssignment::factory()->for($modules[0], 'module')->create(['title' => 'Laporan menunggu pembimbing']);
    PracticumSubmission::factory()->for($pendingAssignment, 'assignment')->for($student, 'student')->create();
    $pending = PracticumSubmission::factory()->for($pendingAssignment, 'assignment')->for($student, 'student')->create(['attempt' => 2]);
    $outsideSubmission = PracticumSubmission::factory()->create();
    $outsideGraded = PracticumSubmission::factory()->for($outsideSubmission->assignment, 'assignment')->for($outsideSubmission->student, 'student')->graded()->create(['attempt' => 2]);
    PracticumAssessment::factory()->for($outsideGraded, 'submission')->create(['final_score' => 100]);

    $completedSession = PracticumSession::factory()->for($modules[0], 'module')->completed()->create();
    PracticumAttendance::factory()->for($completedSession, 'session')->for($student, 'student')->create();
    $exam = PracticumSession::factory()->for($modules[0], 'module')->upcoming()->create(['title' => 'Ujian kelas bimbingan', 'is_exam' => true]);
    PracticumAttendance::factory()->for($exam, 'session')->for($student, 'student')->create();
    $cancelled = PracticumSession::factory()->for($modules[0], 'module')->completed()->create(['cancelled' => true]);
    PracticumAttendance::factory()->for($cancelled, 'session')->for($student, 'student')->create();
    PracticumSession::factory()->upcoming()->create(['title' => 'Private lecturer schedule']);

    $placement = InternshipPlacement::factory()->for($lecturer, 'lecturer')->create();
    InterviewSession::factory()->for($student, 'student')->completed()->create(['overall_score' => 90, 'target_position' => 'Practicum student interview']);
    InterviewSession::factory()->for($placement->student, 'student')->completed()->create(['overall_score' => 70, 'target_position' => 'Internship student interview']);
    InterviewSession::factory()->for($withdrawnStudent, 'student')->completed()->create(['target_position' => 'Withdrawn student interview']);
    InterviewSession::factory()->completed()->create(['target_position' => 'Unassigned private interview']);
    Certificate::factory()->for($student, 'student')->pending()->create();
    Certificate::factory()->for($placement->student, 'student')->pending()->create();
    Certificate::factory()->for($withdrawnStudent, 'student')->pending()->create();
    Certificate::factory()->pending()->create();

    $response = $this->actingAs($lecturer)->get(route('dashboard'));

    $response->assertOk()->assertViewHas('practicumModuleCount', 2)->assertViewHas('practicumStudentCount', 1)
        ->assertViewHas('practicumPendingCount', 1)->assertViewHas('practicumAverageGrade', 70.0)->assertViewHas('practicumAttendanceCount', 1)
        ->assertViewHas('pendingCertificateCount', 2)->assertSee($pendingAssignment->title)->assertSee($exam->title)
        ->assertSee('Practicum student interview')->assertSee('Internship student interview')
        ->assertDontSee('Private lecturer schedule')->assertDontSee('Withdrawn student interview')->assertDontSee('Unassigned private interview');
    expect($response->viewData('practicumPendingSubmissions')->modelKeys())->toBe([$pending->id]);
    expect($response->viewData('recentInterviews')->pluck('student_id')->sort()->values()->all())->toBe([$student->id, $placement->student_id]);
    expect($response->viewData('interviewSummary')['average_score'])->toBe(80.0);
});

test('mentor dashboard counts active placements attendance and evaluation needs in each site timezone', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-31T16:30:00Z'));
    $mentor = User::factory()->mentorDudi()->create();
    $jakarta = InternshipPlacement::factory()->for($mentor, 'mentor')->create(['timezone' => 'Asia/Jakarta', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-31']);
    $makassar = InternshipPlacement::factory()->for($mentor, 'mentor')->create(['timezone' => 'Asia/Makassar', 'starts_on' => '2026-11-01', 'ends_on' => '2026-11-30']);
    $ended = InternshipPlacement::factory()->for($mentor, 'mentor')->create(['timezone' => 'Asia/Jayapura', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-31', 'company_name' => 'Ended private placement']);
    $future = InternshipPlacement::factory()->for($mentor, 'mentor')->create(['timezone' => 'Asia/Jakarta', 'starts_on' => '2026-11-01', 'ends_on' => '2026-11-30', 'company_name' => 'Future placement']);
    $outside = InternshipPlacement::factory()->create(['company_name' => 'Unassigned private company']);
    InternshipAttendance::factory()->for($jakarta, 'placement')->create(['work_date' => '2026-10-31']);
    InternshipAttendance::factory()->for($makassar, 'placement')->create(['work_date' => '2026-11-01']);
    InternshipAttendance::factory()->for($makassar, 'placement')->create(['work_date' => '2026-10-31']);
    InternshipAttendance::factory()->for($outside, 'placement')->create(['work_date' => '2026-10-31']);
    IndustryEvaluation::factory()->for($jakarta, 'placement')->create(['period_month' => '2026-10-01']);
    IndustryEvaluation::factory()->for($ended, 'placement')->create(['period_type' => 'final', 'period_month' => '2026-10-01']);
    InternshipLeaveRequest::factory()->for($jakarta, 'placement')->create();
    InternshipLeaveRequest::factory()->for($makassar, 'placement')->approved()->create();
    InternshipLeaveRequest::factory()->for($outside, 'placement')->create();
    InternshipLogbook::factory()->for($jakarta, 'placement')->verified()->create(['effective_minutes' => 120]);
    $project = InternshipProject::factory()->for($jakarta, 'placement')->create(['title' => 'Proyek bimbingan aktif', 'target_minutes' => 1200, 'completed_minutes' => 360]);
    InternshipProject::factory()->for($makassar, 'placement')->completed()->create(['title' => 'Proyek sudah selesai']);
    InternshipProject::factory()->for($outside, 'placement')->create(['title' => 'Private industry project']);

    $response = $this->actingAs($mentor)->get(route('dashboard'));

    $response->assertOk()->assertViewHas('internshipStudentCount', 2)->assertViewHas('internshipAttendanceToday', 2)
        ->assertViewHas('mentorPendingLeaveCount', 1)->assertViewHas('mentorMonthlyEvaluationCount', 1)->assertViewHas('mentorFinalEvaluationCount', 1)
        ->assertViewHas('internshipVerifiedHours', 2.0)->assertSee($project->title)->assertSee('30% target')
        ->assertDontSee($outside->company_name)->assertDontSee($ended->company_name)->assertDontSee($future->company_name)
        ->assertDontSee('Proyek sudah selesai')->assertDontSee('Private industry project');
    expect($response->viewData('mentorInterns')->modelKeys())->toBe([$jakarta->id, $makassar->id]);
    expect($response->viewData('mentorProjects')->modelKeys())->toBe([$project->id]);
});

test('admin dashboard reports real aggregates without loading individual interview contents', function () {
    $this->freezeTime();
    $admin = User::factory()->admin()->create();
    $students = User::factory()->count(2)->create(['study_program' => 'Teknik Informatika']);
    $inactiveStudent = User::factory()->create(['is_active' => false, 'study_program' => null]);
    $lecturer = User::factory()->dosen()->create();
    User::factory()->mentorDudi()->create();
    $module = PracticumModule::factory()->for($lecturer, 'lecturer')->create();
    PracticumEnrollment::factory()->for($module, 'module')->for($students[0], 'student')->create();
    $privateSession = InterviewSession::factory()->for($students[0], 'student')->completed()->create([
        'target_position' => 'Private individual interview target', 'overall_score' => 60, 'star_score' => 50, 'technical_score' => 60, 'communication_score' => 70,
        'context_snapshot' => ['private' => 'Private student context'], 'evaluation' => ['summary' => 'Private individual feedback'],
    ]);
    InterviewQuestion::factory()->for($privateSession, 'session')->answered()->create(['answer_text' => 'Private recorded answer', 'audio_path' => 'private/audio.webm', 'video_path' => 'private/video.webm']);
    InterviewSession::factory()->for($students[1], 'student')->completed()->create(['overall_score' => 100, 'star_score' => 90, 'technical_score' => 100, 'communication_score' => 100]);
    InterviewSession::factory()->for($inactiveStudent, 'student')->completed()->create(['overall_score' => null, 'star_score' => null, 'technical_score' => null, 'communication_score' => null]);
    InterviewSession::factory()->for($students[0], 'student')->failed()->create(['overall_score' => 99]);
    TracerResponse::factory()->for($students[0], 'user')->create(['wait_months' => 0, 'first_monthly_salary' => 0, 'skill_gaps' => ['komunikasi']]);
    TracerResponse::factory()->for($students[1], 'user')->seeking()->create(['skill_gaps' => ['komunikasi']]);
    $company = PartnerCompany::factory()->create(['internship_quota' => 5]);
    PartnerCompany::factory()->create(['is_active' => false, 'internship_quota' => 20]);
    $expiring = PartnerAgreement::factory()->for($company, 'company')->create(['expires_on' => today()->toDateString(), 'reference_number' => 'MOU-ENDS-TODAY']);
    PartnerAgreement::factory()->for($company, 'company')->create(['expires_on' => today()->subDay()->toDateString()]);
    PartnerAgreement::factory()->for($company, 'company')->create(['expires_on' => today()->addDays(31)->toDateString()]);
    $retrievedSessions = [];
    Event::listen('eloquent.retrieved: '.InterviewSession::class, function (InterviewSession $session) use (&$retrievedSessions): void {
        $retrievedSessions[] = $session->id;
    });

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk()->assertSee($expiring->reference_number)->assertSee('Teknik Informatika')->assertSee('Rp 0')
        ->assertDontSee('Private individual interview target')->assertDontSee('Private student context')->assertDontSee('Private individual feedback')
        ->assertDontSee('Private recorded answer')->assertDontSee('private/audio.webm')->assertDontSee('private/video.webm')
        ->assertDontSee(route('interviews.session', $privateSession), false)
        ->assertViewMissing('recentInterviews')
        ->assertViewHas('partnerSummary', ['active_companies' => 1, 'total_quota' => 5, 'expiring_agreements' => 1]);
    expect($response->viewData('adminSummary'))->toBe([
        'student_count' => 2, 'lecturer_count' => 1, 'mentor_count' => 1, 'student_lecturer_ratio' => 2.0,
        'practicum_module_count' => 1, 'practicum_enrollment_count' => 1, 'interview_completed_count' => 3,
        'interview_scored_count' => 2, 'interview_average_score' => 80.0,
    ]);
    expect($response->viewData('interviewProgramScores')->keyBy('study_program')->get('Teknik Informatika'))->toBe([
        'study_program' => 'Teknik Informatika', 'session_count' => 2, 'scored_count' => 2,
        'overall_score' => 80.0, 'star_score' => 70.0, 'technical_score' => 80.0, 'communication_score' => 85.0,
    ]);
    expect($response->viewData('tracerSummary'))->toMatchArray([
        'total_responses' => 2, 'working_count' => 1, 'absorption_percentage' => 50.0,
        'average_wait_months' => 0.0, 'wait_sample' => 1, 'average_first_salary' => 0.0, 'salary_sample' => 1,
        'alignment_percentage' => 100.0, 'alignment_sample' => 1, 'skill_gaps' => ['komunikasi' => 2],
    ]);
    expect($retrievedSessions)->toBeEmpty();
});
