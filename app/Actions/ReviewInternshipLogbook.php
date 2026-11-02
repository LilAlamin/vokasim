<?php

namespace App\Actions;

use App\Enums\LogbookStatus;
use App\Models\InternshipLogbook;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ReviewInternshipLogbook
{
    public function handle(User $user, int $logbookId, string $decision, ?string $feedback): void
    {
        DB::transaction(function () use ($user, $logbookId, $decision, $feedback): void {
            $logbook = InternshipLogbook::query()->forUser($user)->lockForUpdate()->findOrFail($logbookId);
            Gate::forUser($user)->authorize('review', $logbook);
            $data = Validator::make(['decision' => $decision, 'feedback' => $feedback === null ? null : trim($feedback)], [
                'decision' => ['required', Rule::in(['approve', 'revise'])],
                'feedback' => ['required_if:decision,revise', 'nullable', 'string', 'max:2000'],
            ], ['feedback.required_if' => 'Tuliskan catatan perbaikan sebelum mengembalikan logbook.'])->validate();

            $this->applyDecision($user, $logbook, $data['decision'], $data['feedback'] ?? null);
        });
    }

    /** @param array<array-key, mixed> $logbookIds */
    public function approveMany(User $user, array $logbookIds): int
    {
        $data = Validator::make(['selectedLogbookIds' => $logbookIds], [
            'selectedLogbookIds' => ['required', 'array', 'min:1', 'max:50'],
            'selectedLogbookIds.*' => ['required', 'integer', 'min:1', 'distinct'],
        ], [
            'selectedLogbookIds.required' => 'Pilih logbook yang akan divalidasi.',
            'selectedLogbookIds.max' => 'Pilih maksimal 50 logbook dalam satu proses.',
            'selectedLogbookIds.*.integer' => 'Pilihan logbook tidak valid. Pilih ulang dari daftar.',
            'selectedLogbookIds.*.distinct' => 'Logbook yang sama tidak boleh dipilih lebih dari sekali.',
        ])->validate();

        return DB::transaction(function () use ($user, $data): int {
            $logbooks = InternshipLogbook::query()->forUser($user)
                ->with('placement')->whereKey($data['selectedLogbookIds'])
                ->orderBy('id')->lockForUpdate()->get();

            abort_unless($logbooks->count() === count($data['selectedLogbookIds']), 404);

            foreach ($logbooks as $logbook) {
                Gate::forUser($user)->authorize('review', $logbook);
            }

            foreach ($logbooks as $logbook) {
                $this->applyDecision($user, $logbook, 'approve', null);
            }

            return $logbooks->count();
        });
    }

    private function applyDecision(User $user, InternshipLogbook $logbook, string $decision, ?string $feedback): void
    {
        $fromStatus = $logbook->status;
        $toStatus = $decision === 'revise' ? LogbookStatus::Draft
            : ($user->isMentorDudi() ? LogbookStatus::MentorApproved : LogbookStatus::Verified);

        $logbook->status = $toStatus;

        if ($toStatus === LogbookStatus::Draft) {
            $logbook->mentor_approved_at = null;
            $logbook->verified_at = null;
        } elseif ($toStatus === LogbookStatus::MentorApproved) {
            $logbook->mentor_approved_at = CarbonImmutable::now('UTC');
        } else {
            $logbook->verified_at = CarbonImmutable::now('UTC');
        }

        $logbook->save();
        $logbook->reviews()->create([
            'reviewer_id' => $user->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'feedback' => $feedback,
        ]);
    }
}
