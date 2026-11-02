<?php

namespace App\Actions;

use App\Enums\InterviewStatus;
use App\Jobs\GenerateInterviewQuestions;
use App\Models\InterviewSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StartInterviewSession
{
    public function __construct(private AiInterviewClient $client, private BuildInterviewContext $context) {}

    /** @param array<string, mixed> $input */
    public function handle(User $user, array $input): InterviewSession
    {
        Gate::forUser($user)->authorize('create', InterviewSession::class);
        $data = Validator::make($input, [
            'target_position' => ['required', 'string', 'min:3', 'max:120'],
            'difficulty' => ['required', Rule::in(['beginner', 'intermediate', 'advanced'])],
            'interview_type' => ['required', Rule::in(['behavioral', 'technical', 'scenario'])],
            'interaction_mode' => ['required', Rule::in(['text', 'voice', 'video'])],
            'question_count' => ['required', 'integer', 'between:5,10'],
            'use_context' => ['required', 'boolean'], 'consent' => ['accepted'],
        ], ['consent.accepted' => 'Setujui pemrosesan jawaban oleh layanan AI sebelum memulai latihan.'])->validate();
        if (! $this->client->ready()) {
            throw ValidationException::withMessages(['interview' => 'Layanan AI belum diaktifkan. Hubungi administrator.']);
        }

        return DB::transaction(function () use ($user, $data): InterviewSession {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $sessions = InterviewSession::query()->where('student_id', $user->id);
            if ((clone $sessions)->whereIn('status', [InterviewStatus::Preparing, InterviewStatus::Active, InterviewStatus::Evaluating])->exists()) {
                throw ValidationException::withMessages(['interview' => 'Selesaikan atau batalkan sesi yang masih berjalan sebelum memulai sesi baru.']);
            }
            if ((clone $sessions)->where('created_at', '>=', now()->startOfDay())->count() >= (int) config('interviews.daily_sessions', 5)) {
                throw ValidationException::withMessages(['interview' => 'Batas latihan hari ini sudah tercapai. Lanjutkan kembali besok.']);
            }
            $session = InterviewSession::query()->create([
                'student_id' => $user->id, 'target_position' => trim($data['target_position']),
                'difficulty' => $data['difficulty'], 'interview_type' => $data['interview_type'], 'interaction_mode' => $data['interaction_mode'],
                'question_count' => $data['question_count'], 'answer_seconds' => max(30, min(600, (int) config('interviews.answer_seconds', 180))),
                'use_context' => $data['use_context'], 'context_snapshot' => $data['use_context'] ? $this->context->handle($user) : [],
                'consented_at' => now(), 'status' => InterviewStatus::Preparing, 'ai_model' => config('interviews.text_model'),
            ]);
            GenerateInterviewQuestions::dispatch($session->id)->afterCommit();

            return $session;
        });
    }
}
