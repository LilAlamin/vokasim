<?php

namespace App\Actions;

use App\Enums\InterviewStatus;
use App\Jobs\EvaluateInterviewSession;
use App\Models\InterviewQuestion;
use App\Models\InterviewSession;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class SubmitInterviewAnswer
{
    public function open(User $user, int $sessionId): ?InterviewQuestion
    {
        return DB::transaction(function () use ($user, $sessionId): ?InterviewQuestion {
            $session = InterviewSession::query()->forUser($user)->lockForUpdate()->findOrFail($sessionId);
            Gate::forUser($user)->authorize('update', $session);
            $question = $session->currentQuestion();
            if ($question && ! $question->opened_at) {
                $question->update(['opened_at' => now()]);
                if (! $session->started_at) {
                    $session->update(['started_at' => now()]);
                }
            }

            return $question;
        });
    }

    /** @param array<string, mixed> $input */
    public function handle(User $user, int $sessionId, int $questionId, array $input, ?UploadedFile $audio = null, ?UploadedFile $video = null): void
    {
        $paths = [];
        try {
            DB::transaction(function () use ($user, $sessionId, $questionId, $input, $audio, $video, &$paths): void {
                $session = InterviewSession::query()->forUser($user)->lockForUpdate()->findOrFail($sessionId);
                Gate::forUser($user)->authorize('update', $session);
                $question = $session->currentQuestion();
                if (! $question || $question->id !== $questionId || ! $question->opened_at) {
                    throw ValidationException::withMessages(['answer' => 'Pertanyaan sudah berubah. Muat ulang sesi sebelum mengirim jawaban.']);
                }
                $timedOut = now()->greaterThanOrEqualTo($question->opened_at->addSeconds($session->answer_seconds));
                $data = Validator::make([...$input, 'audio' => $audio, 'video' => $video], [
                    'answer_text' => [$timedOut ? 'nullable' : 'required', 'string', 'max:10000'],
                    'recorded_seconds' => ['nullable', 'numeric', 'min:0.1', 'max:'.$session->answer_seconds],
                    'audio' => $this->audioRules(),
                    'video' => ['nullable', 'file', 'mimetypes:video/webm,video/mp4,video/quicktime', 'max:12288'],
                ], ['answer_text.required' => 'Tulis jawaban atau transkripsikan rekaman sebelum melanjutkan.', 'audio.max' => 'Ukuran audio maksimal 12 MB.', 'video.max' => 'Ukuran video maksimal 12 MB.'])->validate();
                if ($session->interaction_mode === 'text' && ($audio || $video)) {
                    throw ValidationException::withMessages(['audio' => 'Sesi teks tidak menerima rekaman.']);
                }
                if ($video && $session->interaction_mode !== 'video') {
                    throw ValidationException::withMessages(['video' => 'Video hanya dapat disimpan pada sesi video.']);
                }
                foreach (['audio' => $audio, 'video' => $video] as $kind => $file) {
                    if ($file) {
                        $path = $file->store('interviews/'.$session->public_id, 'local');
                        if ($path === false) {
                            throw ValidationException::withMessages([$kind => 'Rekaman gagal disimpan. Coba unggah kembali.']);
                        }
                        $paths[$kind.'_path'] = $path;
                    }
                }
                $question->update([
                    ...$paths, 'answer_text' => trim((string) ($data['answer_text'] ?? '')),
                    'recorded_seconds' => ($audio || $video) ? ($data['recorded_seconds'] ?? null) : null,
                    'answered_at' => now(), 'timed_out' => $timedOut,
                ]);
                if (! $session->questions()->whereNull('answered_at')->exists()) {
                    $session->update(['status' => InterviewStatus::Evaluating]);
                    EvaluateInterviewSession::dispatch($session->id, $session->retry_count)->afterCommit();
                }
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete(array_values($paths));
            throw $exception;
        }
    }

    public function transcribe(User $user, int $sessionId, int $questionId, ?UploadedFile $audio): string
    {
        $session = InterviewSession::query()->forUser($user)->findOrFail($sessionId);
        Gate::forUser($user)->authorize('update', $session);
        if ($session->interaction_mode === 'text' || $session->currentQuestion()?->id !== $questionId) {
            throw ValidationException::withMessages(['audio' => 'Rekaman tidak cocok dengan pertanyaan aktif.']);
        }
        Validator::make(['audio' => $audio], ['audio' => ['required', ...$this->audioRules()]])->validate();
        $key = 'interview-transcribe:'.$sessionId.':'.$questionId;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            throw ValidationException::withMessages(['audio' => 'Batas transkripsi pertanyaan ini tercapai. Ketik jawaban atau lanjutkan.']);
        }
        RateLimiter::hit($key, 3600);
        try {
            /** @var UploadedFile $audio */
            return app(AiInterviewClient::class)->transcribe($audio, $session);
        } catch (Throwable) {
            throw ValidationException::withMessages(['audio' => 'Transkripsi belum berhasil. Coba lagi atau ketik jawaban dari rekaman.']);
        }
    }

    /** @return list<string> */
    private function audioRules(): array
    {
        return ['nullable', 'file', 'mimetypes:audio/webm,video/webm,audio/mp4,video/mp4,audio/mpeg,audio/wav,audio/x-wav,audio/x-m4a', 'max:12288'];
    }
}
