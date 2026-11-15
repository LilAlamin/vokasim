<?php

use App\Actions\AiInterviewClient;
use App\Actions\BuildInterviewContext;
use App\Actions\ManageInterviewSession;
use App\Actions\StartInterviewSession;
use App\Actions\SubmitInterviewAnswer;
use App\Enums\InterviewStatus;
use App\Jobs\EvaluateInterviewSession;
use App\Jobs\GenerateInterviewQuestions;
use App\Models\Certificate;
use App\Models\InternshipLogbook;
use App\Models\InternshipPlacement;
use App\Models\InterviewNote;
use App\Models\InterviewQuestion;
use App\Models\InterviewSession;
use App\Models\PortfolioProject;
use App\Models\PracticumEnrollment;
use App\Models\PracticumModule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    config(['interviews.api_key' => 'test-secret-key', 'interviews.base_url' => 'https://api.openai.com/v1']);
});

function interviewSetup(array $overrides = []): array
{
    return [...['target_position' => 'Network Technician', 'difficulty' => 'beginner', 'interview_type' => 'technical', 'interaction_mode' => 'text', 'question_count' => 5, 'use_context' => false, 'consent' => true], ...$overrides];
}

function generatedInterviewQuestions(): array
{
    return ['questions' => array_map(fn (int $sequence): array => ['sequence' => $sequence, 'category' => $sequence === 1 ? 'introduction' : 'technical', 'prompt' => 'Jelaskan cara Anda memeriksa gangguan jaringan pada tahap '.$sequence.'.'], range(1, 5))];
}

function interviewResponse(array $result): array
{
    return ['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode($result)]]]]];
}

function generatedInterviewFeedback(): array
{
    return ['summary' => 'Struktur jawaban baik; tambahkan hasil terukur.', 'strengths' => ['Langkah perbaikan jelas.'], 'improvements' => ['Jelaskan hasil pengukuran.'],
        'questions' => array_map(fn (int $sequence): array => ['sequence' => $sequence, 'star_score' => 80, 'technical_score' => 90, 'communication_score' => 70, 'feedback' => 'Sebutkan hasil pengukuran setelah perbaikan.', 'improved_answer' => 'Contoh latihan: setelah VLAN diperbaiki, lima komputer kembali terhubung.'], range(1, 5))];
}

test('starting a session records consent and queues questions without trusting supplied ownership or scores', function () {
    Queue::fake([GenerateInterviewQuestions::class]);
    $student = User::factory()->create();
    $session = app(StartInterviewSession::class)->handle($student, interviewSetup(['student_id' => 999, 'overall_score' => 100, 'status' => 'completed', 'answer_seconds' => 999]));
    expect($session)->student_id->toBe($student->id)->status->toBe(InterviewStatus::Preparing)->overall_score->toBeNull()->answer_seconds->toBe(180)->context_snapshot->toBe([])->consented_at->not->toBeNull();
    Queue::assertPushed(GenerateInterviewQuestions::class, fn ($job) => $job->sessionId === $session->id);
});

test('setup enforces consent and supported options', function (array $change, string $field) {
    Queue::fake();
    $student = User::factory()->create();
    try {
        app(StartInterviewSession::class)->handle($student, interviewSetup($change));
        $this->fail('Expected validation failure.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey($field);
    }
    $this->assertDatabaseCount('interview_sessions', 0);
    Queue::assertNothingPushed();
})->with([
    'consent' => [['consent' => false], 'consent'], 'too few questions' => [['question_count' => 4], 'question_count'],
    'too many questions' => [['question_count' => 11], 'question_count'], 'mode' => [['interaction_mode' => 'unrestricted'], 'interaction_mode'],
    'difficulty' => [['difficulty' => 'expert-secret'], 'difficulty'], 'type' => [['interview_type' => 'other'], 'interview_type'],
]);

test('missing provider credentials shows an honest unavailable state and sends no request', function () {
    Http::preventStrayRequests();
    config(['interviews.api_key' => null]);
    $student = User::factory()->create();
    Livewire::actingAs($student)->test('pages::interviews.index')->assertSee('Layanan AI belum diaktifkan')
        ->set('target_position', 'Network Technician')->set('consent', true)->call('start')->assertHasErrors(['interview']);
    $this->assertDatabaseCount('interview_sessions', 0);
    Http::assertNothingSent();
});

test('daily and concurrent session limits reject a new practice', function (string $condition) {
    $this->freezeTime();
    Queue::fake();
    $student = User::factory()->create();
    if ($condition === 'active') {
        InterviewSession::factory()->active()->for($student, 'student')->create();
    } else {
        InterviewSession::factory()->completed()->for($student, 'student')->count(5)->create();
    }
    expect(fn () => app(StartInterviewSession::class)->handle($student, interviewSetup()))->toThrow(ValidationException::class);
    Queue::assertNothingPushed();
})->with(['active', 'daily']);

test('context includes only the students own eligible evidence without private file metadata', function () {
    $this->freezeTime();
    $student = User::factory()->create(['study_program' => 'Teknik Informatika']);
    PortfolioProject::factory()->for($student, 'student')->create(['title' => 'Proyek milik saya']);
    PortfolioProject::factory()->create(['title' => 'Proyek orang lain']);
    Certificate::factory()->for($student, 'student')->verified()->create(['title' => 'Sertifikat aktif', 'credential_number' => 'PRIVATE-CREDENTIAL']);
    Certificate::factory()->for($student, 'student')->expired()->create(['title' => 'Sertifikat kedaluwarsa']);
    Certificate::factory()->for($student, 'student')->pending()->create(['title' => 'Sertifikat belum sah']);
    $placement = InternshipPlacement::factory()->for($student, 'student')->create();
    InternshipLogbook::factory()->for($placement, 'placement')->verified()->create(['description' => 'Aktivitas saya yang terverifikasi']);
    InternshipLogbook::factory()->verified()->create(['description' => 'Aktivitas orang lain']);
    $context = json_encode(app(BuildInterviewContext::class)->handle($student));
    expect($context)->toContain('Proyek milik saya', 'Sertifikat aktif', 'Aktivitas saya yang terverifikasi')
        ->not->toContain('Proyek orang lain', 'Sertifikat kedaluwarsa', 'Sertifikat belum sah', 'Aktivitas orang lain', 'PRIVATE-CREDENTIAL', 'file_path', $student->email);
});

test('provider questions are validated stored once and timers begin only when opened', function () {
    Http::preventStrayRequests();
    Http::fake(['https://api.openai.com/v1/responses' => Http::response(interviewResponse(generatedInterviewQuestions()))]);
    $session = InterviewSession::factory()->create();
    $job = new GenerateInterviewQuestions($session->id);
    $job->handle(app(AiInterviewClient::class));
    $job->handle(app(AiInterviewClient::class));
    expect($session->fresh()->status)->toBe(InterviewStatus::Active);
    expect($session->questions()->count())->toBe(5);
    expect($session->questions()->whereNotNull('opened_at')->count())->toBe(0);
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.openai.com/v1/responses' && $request['store'] === false && $request['text']['format']['strict'] === true && $request['model'] === $session->ai_model && ! str_contains($request['input'], $session->student->email));
    $this->freezeTime();
    $question = app(SubmitInterviewAnswer::class)->open($session->student, $session->id);
    $opened = $question->opened_at;
    $this->travel(20)->seconds();
    app(SubmitInterviewAnswer::class)->open($session->student, $session->id);
    expect($question->fresh()->opened_at->equalTo($opened))->toBeTrue();
});

test('provider failures never manufacture questions or expose provider secrets', function (array $body, int $status) {
    Http::preventStrayRequests();
    Http::fake(['https://api.openai.com/v1/responses' => Http::response($body, $status)]);
    $session = InterviewSession::factory()->create();
    (new GenerateInterviewQuestions($session->id))->handle(app(AiInterviewClient::class));
    expect($session->fresh())->status->toBe(InterviewStatus::Failed)->failure_stage->toBe('generation');
    expect($session->fresh()->error_message)->not->toContain('test-secret-key', 'provider-secret');
    $this->assertDatabaseCount('interview_questions', 0);
    Http::assertSentCount(1);
})->with([
    'provider error' => [['error' => 'provider-secret'], 503], 'incomplete' => [['status' => 'incomplete'], 200],
    'refusal' => [['status' => 'completed', 'output' => [['content' => [['type' => 'refusal', 'refusal' => 'cannot comply']]]]], 200],
    'wrong count' => [interviewResponse(['questions' => []]), 200],
]);

test('cancelling while the provider is responding cannot resurrect a session', function () {
    $session = InterviewSession::factory()->create();
    Http::preventStrayRequests();
    Http::fake(['https://api.openai.com/v1/responses' => function () use ($session) {
        app(ManageInterviewSession::class)->cancel($session->student, $session->id);

        return Http::response(interviewResponse(generatedInterviewQuestions()));
    }]);
    (new GenerateInterviewQuestions($session->id))->handle(app(AiInterviewClient::class));
    expect($session->fresh()->status)->toBe(InterviewStatus::Cancelled);
    $this->assertDatabaseCount('interview_questions', 0);
    Http::assertSentCount(1);
});

test('answers enforce sequence ownership and immutable submissions before evaluation is queued', function () {
    Queue::fake([EvaluateInterviewSession::class]);
    $session = InterviewSession::factory()->active()->create();
    $questions = InterviewQuestion::factory()->for($session, 'session')->count(5)->sequence(fn (Sequence $sequence) => ['sequence' => $sequence->index + 1])->create();
    $answers = app(SubmitInterviewAnswer::class);
    $answers->open($session->student, $session->id);
    expect(fn () => $answers->handle($session->student, $session->id, $questions[1]->id, ['answer_text' => 'Out of order']))->toThrow(ValidationException::class);
    foreach ($questions as $question) {
        $answers->open($session->student, $session->id);
        $answers->handle($session->student, $session->id, $question->id, ['answer_text' => 'Saya menguji jaringan menggunakan alat ukur dan mendokumentasikan hasilnya.']);
    }
    expect($session->fresh()->status)->toBe(InterviewStatus::Evaluating);
    expect($session->questions()->whereNotNull('answered_at')->count())->toBe(5);
    Queue::assertPushed(EvaluateInterviewSession::class, fn ($job) => $job->sessionId === $session->id);
    Queue::assertPushedTimes(EvaluateInterviewSession::class, 1);
    $this->actingAs($session->student)->get(route('interviews.session', $session))->assertOk()->assertSee('Menyusun umpan balik');
});

test('expired answers can be submitted empty and are marked by the server clock', function () {
    Queue::fake([EvaluateInterviewSession::class]);
    $this->freezeTime();
    $session = InterviewSession::factory()->active()->create(['question_count' => 1]);
    $question = InterviewQuestion::factory()->for($session, 'session')->opened()->create();
    expect(fn () => app(SubmitInterviewAnswer::class)->handle($session->student, $session->id, $question->id, ['answer_text' => '']))->toThrow(ValidationException::class);
    $this->travel(181)->seconds();
    app(SubmitInterviewAnswer::class)->handle($session->student, $session->id, $question->id, ['answer_text' => '']);
    expect($question->fresh())->timed_out->toBeTrue()->answer_text->toBe('');
    Queue::assertPushed(EvaluateInterviewSession::class);
});

test('evaluation computes weighted scores and encrypts transcripts and feedback', function () {
    Http::preventStrayRequests();
    Http::fake(['https://api.openai.com/v1/responses' => Http::response(interviewResponse(generatedInterviewFeedback()))]);
    $session = InterviewSession::factory()->evaluating()->create();
    InterviewQuestion::factory()->for($session, 'session')->answered()->count(5)->sequence(fn (Sequence $sequence) => ['sequence' => $sequence->index + 1])->create();
    (new EvaluateInterviewSession($session->id))->handle(app(AiInterviewClient::class));
    expect($session->fresh())->status->toBe(InterviewStatus::Completed)->overall_score->toBe(82.0)->star_score->toBe(80.0)->technical_score->toBe(90.0)->communication_score->toBe(70.0);
    $rawQuestion = DB::table('interview_questions')->first();
    expect($rawQuestion->answer_text)->not->toContain('jaringan laboratorium');
    expect($rawQuestion->evaluation)->not->toContain('Sebutkan hasil');
    expect(DB::table('interview_sessions')->value('evaluation'))->not->toContain('Struktur jawaban');
    Http::assertSentCount(1);
});

test('out of range or mismatched evaluation output leaves saved answers available to retry', function (string $defect) {
    $feedback = generatedInterviewFeedback();
    $feedback['questions'][0][$defect === 'score' ? 'star_score' : 'sequence'] = $defect === 'score' ? 101 : 8;
    Http::preventStrayRequests();
    Http::fake(['https://api.openai.com/v1/responses' => Http::response(interviewResponse($feedback))]);
    $session = InterviewSession::factory()->evaluating()->create();
    InterviewQuestion::factory()->for($session, 'session')->answered()->count(5)->sequence(fn (Sequence $sequence) => ['sequence' => $sequence->index + 1])->create();
    (new EvaluateInterviewSession($session->id))->handle(app(AiInterviewClient::class));
    expect($session->fresh())->status->toBe(InterviewStatus::Failed)->failure_stage->toBe('evaluation')->overall_score->toBeNull();
    expect($session->questions()->whereNotNull('answer_text')->count())->toBe(5);
    Http::assertSentCount(1);
})->with(['score', 'question']);

test('retry requeues the failed stage while stale jobs and a second active session are refused', function () {
    Queue::fake([GenerateInterviewQuestions::class, EvaluateInterviewSession::class]);
    $session = InterviewSession::factory()->failed()->create(['failure_stage' => 'evaluation']);
    app(ManageInterviewSession::class)->retry($session->student, $session->id);
    expect($session->fresh())->status->toBe(InterviewStatus::Evaluating)->retry_count->toBe(1);
    Queue::assertPushed(EvaluateInterviewSession::class, fn ($job) => $job->attempt === 1);
    Queue::assertNotPushed(GenerateInterviewQuestions::class);
    Http::preventStrayRequests();
    (new EvaluateInterviewSession($session->id, 0))->handle(app(AiInterviewClient::class));
    Http::assertNothingSent();
    $other = InterviewSession::factory()->for($session->student, 'student')->failed()->create();
    expect(fn () => app(ManageInterviewSession::class)->retry($session->student, $other->id))->toThrow(ValidationException::class);
});

test('transcript access is limited to its student and assigned lecturer', function (string $actor, bool $allowed) {
    $placement = InternshipPlacement::factory()->create();
    $session = InterviewSession::factory()->for($placement->student, 'student')->completed()->create();
    $user = match ($actor) {
        'student' => $placement->student, 'lecturer' => $placement->lecturer, 'mentor' => $placement->mentor,
        'admin' => User::factory()->admin()->create(), 'other student' => User::factory()->create(), 'other lecturer' => User::factory()->dosen()->create(),
    };
    expect(Gate::forUser($user)->allows('view', $session))->toBe($allowed);
    expect(Gate::forUser($user)->allows('review', $session))->toBe($actor === 'lecturer');
    expect(Gate::forUser($user)->allows('create', InterviewSession::class))->toBe(in_array($actor, ['student', 'other student']));
})->with([['student', true], ['lecturer', true], ['mentor', false], ['admin', false], ['other student', false], ['other lecturer', false]]);

test('active practicum assignment also grants lecturers transcript access', function () {
    $module = PracticumModule::factory()->create();
    $enrollment = PracticumEnrollment::factory()->for($module, 'module')->create(['status' => 'active']);
    $session = InterviewSession::factory()->for($enrollment->student, 'student')->completed()->create();
    expect(Gate::forUser($module->lecturer)->allows('view', $session))->toBeTrue();
    $enrollment->update(['status' => 'withdrawn']);
    expect(Gate::forUser($module->lecturer)->allows('view', $session))->toBeFalse();
});

test('recordings are private and students can remove only their own recordings', function () {
    Storage::fake('local');
    $placement = InternshipPlacement::factory()->create();
    $session = InterviewSession::factory()->for($placement->student, 'student')->completed()->create();
    $question = InterviewQuestion::factory()->for($session, 'session')->answered()->create(['audio_path' => 'interviews/test.webm']);
    Storage::disk('local')->put($question->audio_path, 'private-audio');
    $url = route('interviews.media', ['question' => $question, 'kind' => 'audio']);
    $this->get($url)->assertRedirect(route('login'));
    $this->actingAs($placement->lecturer)->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->actingAs($placement->mentor)->get($url)->assertNotFound();
    $this->actingAs(User::factory()->admin()->create())->get($url)->assertNotFound();
    $this->actingAs($placement->student)->get($url)->assertOk();
    app(ManageInterviewSession::class)->deleteRecordings($placement->student, $session->id);
    Storage::disk('local')->assertMissing('interviews/test.webm');
    expect($question->fresh())->audio_path->toBeNull()->answer_text->not->toBeNull();
    $this->get($url)->assertNotFound();
});

test('a lecturer note is private scoped and escaped in the session page', function () {
    $placement = InternshipPlacement::factory()->create();
    $session = InterviewSession::factory()->for($placement->student, 'student')->completed()->create();
    $question = InterviewQuestion::factory()->for($session, 'session')->answered()->create();
    $body = '<script>alert("private")</script> Tambahkan hasil pengukuran.';
    Livewire::actingAs($placement->lecturer)->test('pages::interviews.session', ['session' => $session])
        ->set('noteBody', $body)->set('noteQuestion', (string) $question->id)->call('addNote')->assertHasNoErrors()->assertSee($body)->assertDontSeeHtml($body);
    expect(InterviewNote::sole())->reviewer_id->toBe($placement->lecturer_id)->body->toBe($body);
    expect(DB::table('interview_notes')->value('body'))->not->toContain('Tambahkan hasil');
    $this->actingAs(User::factory()->dosen()->create())->get(route('interviews.session', $session))->assertNotFound();
});

test('transcription validates audio and sends the configured model without forwarding video', function () {
    Http::preventStrayRequests();
    Http::fake(['https://api.openai.com/v1/audio/transcriptions' => Http::response(['text' => 'Saya menguji jaringan lokal.'])]);
    $session = InterviewSession::factory()->active()->create(['interaction_mode' => 'voice']);
    $question = InterviewQuestion::factory()->for($session, 'session')->opened()->create();
    $file = UploadedFile::fake()->create('answer.webm', 100, 'audio/webm');
    $text = app(SubmitInterviewAnswer::class)->transcribe($session->student, $session->id, $question->id, $file);
    expect($text)->toBe('Saya menguji jaringan lokal.');
    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.openai.com/v1/audio/transcriptions'
        && collect($request->data())->firstWhere('name', 'model')['contents'] === config('interviews.transcription_model')
        && collect($request->data())->firstWhere('name', 'languages[]')['contents'] === 'id'
        && collect($request->data())->firstWhere('name', 'file')['filename'] === 'answer.webm');
    expect(fn () => app(SubmitInterviewAnswer::class)->transcribe($session->student, $session->id, $question->id, UploadedFile::fake()->create('payload.php', 5, 'application/x-php')))->toThrow(ValidationException::class);
    Http::assertSentCount(1);
});

test('voice sessions proxy SDP and never expose the provider key to the browser', function () {
    Http::preventStrayRequests();
    Http::fake(['https://api.openai.com/v1/realtime/calls' => Http::response("v=0\r\nanswer", 201, ['Content-Type' => 'application/sdp'])]);
    $session = InterviewSession::factory()->active()->create(['interaction_mode' => 'voice']);
    InterviewQuestion::factory()->for($session, 'session')->opened()->create();
    $this->actingAs($session->student)->postJson(route('interviews.voice', $session), ['sdp' => "v=0\r\noffer"])->assertOk()->assertSee('v=0')->assertDontSee('test-secret-key');
    Http::assertSentCount(1);
    $this->actingAs(User::factory()->create())->postJson(route('interviews.voice', $session), ['sdp' => 'v=0'])->assertNotFound();
});

test('retention cleanup removes old media while preserving recent recordings and transcripts', function () {
    $this->freezeTime();
    Storage::fake('local');
    $old = InterviewQuestion::factory()->answered()->create(['answered_at' => now()->subDays(91), 'audio_path' => 'interviews/old.webm']);
    $recent = InterviewQuestion::factory()->answered()->create(['audio_path' => 'interviews/recent.webm']);
    Storage::disk('local')->put($old->audio_path, 'old');
    Storage::disk('local')->put($recent->audio_path, 'recent');
    $this->artisan('interviews:prune-recordings', ['--dry-run' => true])->assertSuccessful();
    Storage::disk('local')->assertExists($old->audio_path);
    $this->artisan('interviews:prune-recordings')->assertSuccessful();
    Storage::disk('local')->assertMissing($old->audio_path);
    Storage::disk('local')->assertExists($recent->audio_path);
    expect($old->fresh())->audio_path->toBeNull()->answer_text->not->toBeNull();
});

test('gemini openai-compatible base url uses chat completions endpoint for questions', function () {
    config()->set('interviews.base_url', 'https://generativelanguage.googleapis.com/v1beta/openai');
    Http::preventStrayRequests();
    Http::fake([
        'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode(generatedInterviewQuestions(), JSON_THROW_ON_ERROR),
                ],
            ]],
        ]),
    ]);

    $session = InterviewSession::factory()->create(['ai_model' => 'gemini-2.5-flash']);
    (new GenerateInterviewQuestions($session->id))->handle(app(AiInterviewClient::class));

    expect($session->fresh())->status->toBe(InterviewStatus::Active);
    expect($session->questions()->count())->toBe(5);
    Http::assertSent(fn (Request $request) => $request->url() === 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions'
        && $request['model'] === 'gemini-2.5-flash'
        && $request['response_format']['type'] === 'json_schema'
        && ! $request->hasHeader('anthropic-version'));
});
