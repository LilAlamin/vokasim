<?php

namespace App\Actions;

use App\Enums\InterviewStatus;
use App\Jobs\EvaluateInterviewSession;
use App\Jobs\GenerateInterviewQuestions;
use App\Models\InterviewSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ManageInterviewSession
{
    public function retry(User $user, int $sessionId): void
    {
        DB::transaction(function () use ($user, $sessionId): void {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $session = InterviewSession::query()->forUser($user)->lockForUpdate()->findOrFail($sessionId);
            Gate::forUser($user)->authorize('manage', $session);
            if ($session->status !== InterviewStatus::Failed || $session->retry_count >= (int) config('interviews.max_retries', 3)) {
                throw ValidationException::withMessages(['interview' => 'Sesi ini tidak dapat dicoba ulang. Mulai latihan baru bila batas percobaan sudah tercapai.']);
            }
            if (! app(AiInterviewClient::class)->ready()) {
                throw ValidationException::withMessages(['interview' => 'Layanan AI belum diaktifkan. Hubungi administrator.']);
            }
            if (InterviewSession::query()->where('student_id', $user->id)->whereKeyNot($session->id)
                ->whereIn('status', [InterviewStatus::Preparing, InterviewStatus::Active, InterviewStatus::Evaluating])->exists()) {
                throw ValidationException::withMessages(['interview' => 'Selesaikan sesi lain yang sedang berjalan sebelum mencoba ulang.']);
            }
            $evaluating = $session->failure_stage === 'evaluation';
            $session->update(['retry_count' => $session->retry_count + 1, 'status' => $evaluating ? InterviewStatus::Evaluating : InterviewStatus::Preparing, 'error_message' => null]);
            if ($evaluating) {
                EvaluateInterviewSession::dispatch($session->id, $session->retry_count)->afterCommit();
            } else {
                GenerateInterviewQuestions::dispatch($session->id, $session->retry_count)->afterCommit();
            }
        });
    }

    public function cancel(User $user, int $sessionId): void
    {
        DB::transaction(function () use ($user, $sessionId): void {
            $session = InterviewSession::query()->forUser($user)->lockForUpdate()->findOrFail($sessionId);
            Gate::forUser($user)->authorize('manage', $session);
            if (in_array($session->status, [InterviewStatus::Completed, InterviewStatus::Cancelled], true)) {
                throw ValidationException::withMessages(['interview' => 'Sesi ini sudah selesai.']);
            }
            $session->update(['status' => InterviewStatus::Cancelled, 'completed_at' => now(), 'context_snapshot' => []]);
        });
    }

    public function deleteRecordings(User $user, int $sessionId): void
    {
        DB::transaction(function () use ($user, $sessionId): void {
            $session = InterviewSession::query()->forUser($user)->lockForUpdate()->findOrFail($sessionId);
            Gate::forUser($user)->authorize('delete', $session);
            foreach ($session->questions()->get() as $question) {
                $paths = array_values(array_filter([$question->audio_path, $question->video_path]));
                if ($paths !== [] && ! Storage::disk('local')->delete($paths)) {
                    throw ValidationException::withMessages(['interview' => 'Sebagian rekaman belum terhapus. Coba lagi.']);
                }
                $question->update(['audio_path' => null, 'video_path' => null]);
            }
        });
    }

    public function note(User $user, int $sessionId, string $body, ?int $questionId = null): void
    {
        $session = InterviewSession::query()->forUser($user)->findOrFail($sessionId);
        Gate::forUser($user)->authorize('review', $session);
        $data = Validator::make(['body' => trim($body)], ['body' => ['required', 'string', 'min:5', 'max:5000']])->validate();
        if ($questionId !== null) {
            $session->questions()->findOrFail($questionId);
        }
        $session->notes()->create(['reviewer_id' => $user->id, 'interview_question_id' => $questionId, 'body' => $data['body']]);
    }
}
