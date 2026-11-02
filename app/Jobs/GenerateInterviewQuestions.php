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

class GenerateInterviewQuestions implements ShouldBeUnique, ShouldQueue
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
        $session = InterviewSession::query()->where('status', InterviewStatus::Preparing)->where('retry_count', $this->attempt)->find($this->sessionId);
        if (! $session) {
            return;
        }
        try {
            $questions = $client->questions($session);
            DB::transaction(function () use ($questions): void {
                $session = InterviewSession::query()->lockForUpdate()->find($this->sessionId);
                if (! $session || $session->status !== InterviewStatus::Preparing || $session->retry_count !== $this->attempt) {
                    return;
                }
                $session->questions()->createMany($questions);
                $session->update(['status' => InterviewStatus::Active, 'error_message' => null, 'failure_stage' => null]);
            });
        } catch (Throwable $exception) {
            $this->failed($exception);
        }
    }

    public function failed(?Throwable $exception): void
    {
        DB::transaction(function (): void {
            $session = InterviewSession::query()->whereKey($this->sessionId)->where('status', InterviewStatus::Preparing)->where('retry_count', $this->attempt)->lockForUpdate()->first();
            $session?->update(['status' => InterviewStatus::Failed, 'failure_stage' => 'generation', 'error_message' => 'Pertanyaan belum berhasil dibuat. Coba lagi saat layanan tersedia.']);
        });
        Log::warning('Interview question generation failed', ['session_id' => $this->sessionId, 'exception_type' => $exception ? $exception::class : 'timeout']);
    }
}
