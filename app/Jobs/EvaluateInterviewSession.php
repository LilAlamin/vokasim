<?php

namespace App\Jobs;

use App\Actions\AiInterviewClient;
use App\Enums\InterviewStatus;
use App\Models\InterviewSession;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class EvaluateInterviewSession implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 75;

    public bool $failOnTimeout = true;

    public int $uniqueFor = 600;

    public function __construct(public int $sessionId, public int $attempt = 0) {}

    public function uniqueId(): string
    {
        return $this->sessionId.':'.$this->attempt;
    }

    public function handle(AiInterviewClient $client): void
    {
        $session = InterviewSession::query()->where('status', InterviewStatus::Evaluating)->where('retry_count', $this->attempt)->find($this->sessionId);
        if (! $session) {
            return;
        }
        try {
            $evaluation = $client->evaluate($session);
            DB::transaction(function () use ($evaluation): void {
                $session = InterviewSession::query()->lockForUpdate()->find($this->sessionId);
                if (! $session || $session->status !== InterviewStatus::Evaluating || $session->retry_count !== $this->attempt) {
                    return;
                }
                foreach ($evaluation['questions'] as $feedback) {
                    $session->questions()->where('sequence', $feedback['sequence'])->firstOrFail()->update(['evaluation' => $feedback]);
                }
                $session->update([
                    'status' => InterviewStatus::Completed, 'completed_at' => now(), 'error_message' => null, 'failure_stage' => null,
                    'star_score' => $evaluation['star_score'], 'technical_score' => $evaluation['technical_score'],
                    'communication_score' => $evaluation['communication_score'], 'overall_score' => $evaluation['overall_score'],
                    'evaluation' => ['summary' => $evaluation['summary'], 'strengths' => $evaluation['strengths'], 'improvements' => $evaluation['improvements']],
                ]);
            });
        } catch (Throwable $exception) {
            $this->failed($exception);
        }
    }

    public function failed(?Throwable $exception): void
    {
        DB::transaction(function (): void {
            $session = InterviewSession::query()->whereKey($this->sessionId)->where('status', InterviewStatus::Evaluating)->where('retry_count', $this->attempt)->lockForUpdate()->first();
            $session?->update(['status' => InterviewStatus::Failed, 'failure_stage' => 'evaluation', 'error_message' => 'Evaluasi belum berhasil. Jawaban tersimpan; coba evaluasi kembali.']);
        });
        Log::warning('Interview evaluation failed', ['session_id' => $this->sessionId, 'exception_type' => $exception ? $exception::class : 'timeout']);
    }
}
