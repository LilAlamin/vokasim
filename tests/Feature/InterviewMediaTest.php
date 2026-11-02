<?php

use App\Actions\ManageInterviewSession;
use App\Actions\SubmitInterviewAnswer;
use App\Jobs\EvaluateInterviewSession;
use App\Models\InterviewQuestion;
use App\Models\InterviewSession;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

test('video answers persist both private tracks and a transcript before evaluation', function () {
    Storage::fake('local');
    Queue::fake([EvaluateInterviewSession::class]);
    $session = InterviewSession::factory()->active()->create(['interaction_mode' => 'video', 'question_count' => 1]);
    $question = InterviewQuestion::factory()->for($session, 'session')->opened()->create();
    $audio = UploadedFile::fake()->create('voice.webm', 30, 'audio/webm');
    $video = UploadedFile::fake()->create('camera.webm', 150, 'video/webm');
    app(SubmitInterviewAnswer::class)->handle($session->student, $session->id, $question->id, [
        'answer_text' => 'Saya memeriksa catu daya kemudian menguji sensor.', 'recorded_seconds' => 60, 'audio_path' => '/secret',
    ], $audio, $video);
    $question->refresh();
    expect($question)->audio_path->toStartWith('interviews/'.$session->public_id.'/')->video_path->toStartWith('interviews/'.$session->public_id.'/')->recorded_seconds->toBe(60.0);
    Storage::disk('local')->assertExists([$question->audio_path, $question->video_path]);
    Queue::assertPushed(EvaluateInterviewSession::class);
});

test('answer uploads reject unsupported media sizes and modes without saving any answer', function (string $case) {
    Storage::fake('local');
    Queue::fake();
    $session = InterviewSession::factory()->active()->create(['interaction_mode' => $case === 'text mode' ? 'text' : 'voice']);
    $question = InterviewQuestion::factory()->for($session, 'session')->opened()->create();
    $audio = match ($case) {
        'unsafe type' => UploadedFile::fake()->create('script.php', 1, 'application/x-php'),
        'too large' => UploadedFile::fake()->create('voice.webm', 12289, 'audio/webm'),
        default => UploadedFile::fake()->create('voice.webm', 10, 'audio/webm'),
    };
    $video = $case === 'video in voice mode' ? UploadedFile::fake()->create('camera.webm', 10, 'video/webm') : null;
    expect(fn () => app(SubmitInterviewAnswer::class)->handle($session->student, $session->id, $question->id, ['answer_text' => 'Jawaban latihan.'], $audio, $video))->toThrow(ValidationException::class);
    expect($question->fresh())->answered_at->toBeNull()->audio_path->toBeNull()->video_path->toBeNull();
    Storage::disk('local')->assertDirectoryEmpty('interviews');
    Queue::assertNothingPushed();
})->with(['unsafe type', 'too large', 'text mode', 'video in voice mode']);

test('media actions reject another student and never overwrite an already submitted answer', function () {
    Storage::fake('local');
    $session = InterviewSession::factory()->active()->create(['interaction_mode' => 'voice']);
    $question = InterviewQuestion::factory()->for($session, 'session')->answered()->create(['sequence' => 1, 'audio_path' => 'interviews/owned.webm']);
    InterviewQuestion::factory()->for($session, 'session')->opened()->create(['sequence' => 2]);
    Storage::disk('local')->put($question->audio_path, 'original');
    $other = User::factory()->create();
    expect(fn () => app(ManageInterviewSession::class)->deleteRecordings($other, $session->id))->toThrow(ModelNotFoundException::class);
    expect(fn () => app(SubmitInterviewAnswer::class)->handle($other, $session->id, $question->id, ['answer_text' => 'Forged']))->toThrow(ModelNotFoundException::class);
    expect(fn () => app(SubmitInterviewAnswer::class)->handle($session->student, $session->id, $question->id, ['answer_text' => 'Changed']))->toThrow(ValidationException::class);
    expect($question->fresh()->answer_text)->toBe($question->answer_text);
    Storage::disk('local')->assertExists('interviews/owned.webm');
});

test('transcription has a per question request budget', function () {
    config(['interviews.api_key' => 'test-secret', 'interviews.base_url' => 'https://api.openai.com/v1']);
    Http::preventStrayRequests();
    Http::fake(['https://api.openai.com/v1/audio/transcriptions' => Http::response(['text' => 'Jawaban hasil transkripsi.'])]);
    $session = InterviewSession::factory()->active()->create(['interaction_mode' => 'voice']);
    $question = InterviewQuestion::factory()->for($session, 'session')->opened()->create();
    $file = UploadedFile::fake()->create('answer.webm', 10, 'audio/webm');
    foreach (range(1, 3) as $attempt) {
        app(SubmitInterviewAnswer::class)->transcribe($session->student, $session->id, $question->id, $file);
    }
    expect(fn () => app(SubmitInterviewAnswer::class)->transcribe($session->student, $session->id, $question->id, $file))->toThrow(ValidationException::class, 'Batas transkripsi');
    Http::assertSentCount(3);
});

test('transcription failures are recoverable without leaking upstream errors', function () {
    config(['interviews.api_key' => 'test-secret', 'interviews.base_url' => 'https://api.openai.com/v1']);
    Http::preventStrayRequests();
    Http::fake(['https://api.openai.com/v1/audio/transcriptions' => Http::failedConnection()]);
    $session = InterviewSession::factory()->active()->create(['interaction_mode' => 'voice']);
    $question = InterviewQuestion::factory()->for($session, 'session')->opened()->create();
    $file = UploadedFile::fake()->create('answer.webm', 10, 'audio/webm');
    expect(fn () => app(SubmitInterviewAnswer::class)->transcribe($session->student, $session->id, $question->id, $file))->toThrow(ValidationException::class, 'Coba lagi atau ketik jawaban');
    expect($question->fresh()->answered_at)->toBeNull();
    Http::assertSentCount(1);
});

test('the voice proxy sends a bounded current question session and limits repeat connections', function () {
    config(['interviews.api_key' => 'test-secret', 'interviews.base_url' => 'https://api.openai.com/v1']);
    Http::preventStrayRequests();
    Http::fake(['https://api.openai.com/v1/realtime/calls' => Http::response("v=0\r\nanswer")]);
    $session = InterviewSession::factory()->active()->create(['interaction_mode' => 'voice']);
    $question = InterviewQuestion::factory()->for($session, 'session')->opened()->create();
    $this->actingAs($session->student);
    foreach (range(1, 2) as $attempt) {
        $this->postJson(route('interviews.voice', $session), ['sdp' => "v=0\r\noffer"])->assertOk();
    }
    $this->postJson(route('interviews.voice', $session), ['sdp' => "v=0\r\noffer"])->assertUnprocessable()->assertJsonValidationErrors('voice');
    Http::assertSentCount(2);
    Http::assertSent(function (Request $request) use ($question): bool {
        $settings = json_decode(collect($request->data())->firstWhere('name', 'session')['contents'], true);

        return $settings['model'] === config('interviews.realtime_model') && $settings['max_output_tokens'] === 800 && str_contains($settings['instructions'], $question->prompt) && $settings['audio']['input']['turn_detection'] === null;
    });
});

test('guests unverified users and industry mentors cannot open the interview workspace', function () {
    $this->get(route('interviews.index'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->unverified()->create())->get(route('interviews.index'))->assertRedirect(route('verification.notice'));
    $this->actingAs(User::factory()->mentorDudi()->create())->get(route('interviews.index'))->assertForbidden();
});

test('the active session view hides unopened questions and reveals a timer when the owner opens it', function () {
    $session = InterviewSession::factory()->active()->create();
    $question = InterviewQuestion::factory()->for($session, 'session')->create();
    Livewire::actingAs($session->student)->test('pages::interviews.session', ['session' => $session])
        ->assertDontSee($question->prompt)->assertSee('Buka pertanyaan 1')->call('openQuestion')->assertHasNoErrors()
        ->assertSee($question->prompt)->assertSee('Sisa waktu');
    expect($question->fresh()->opened_at)->not->toBeNull();
});

test('an answer submitted in another tab clears stale draft text and media before the next question', function () {
    Storage::fake('local');
    $session = InterviewSession::factory()->active()->create(['interaction_mode' => 'voice']);
    $first = InterviewQuestion::factory()->for($session, 'session')->opened()->create(['sequence' => 1]);
    $next = InterviewQuestion::factory()->for($session, 'session')->create(['sequence' => 2]);
    $page = Livewire::actingAs($session->student)->test('pages::interviews.session', ['session' => $session])
        ->set('answer_text', 'Draf pertanyaan pertama dari tab lama.')
        ->set('audio', UploadedFile::fake()->create('old-answer.webm', 10, 'audio/webm'));
    app(SubmitInterviewAnswer::class)->handle($session->student, $session->id, $first->id, ['answer_text' => 'Jawaban dikirim melalui tab lain.']);
    app(SubmitInterviewAnswer::class)->open($session->student, $session->id);

    $page->call('sendAnswer', $first->id)->assertHasErrors(['answer'])
        ->assertSet('answer_text', '')->assertSet('audio', null)->assertSet('video', null)->assertSet('draftQuestionId', $next->id);
    expect($next->fresh()->answered_at)->toBeNull();
    Storage::disk('local')->assertDirectoryEmpty('interviews');
});
