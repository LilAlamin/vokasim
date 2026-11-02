<?php

namespace App\Actions;

use App\Models\PracticumAssessment;
use App\Models\PracticumAssignment;
use App\Models\PracticumSubmission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AssessPracticumSubmission
{
    /** @param array<string, mixed> $input */
    public function handle(User $user, int $submissionId, array $input): PracticumAssessment
    {
        return DB::transaction(function () use ($user, $submissionId, $input): PracticumAssessment {
            $submission = $this->lockSubmission($user, $submissionId);

            if (! in_array($submission->status, ['submitted', 'graded'], true)) {
                throw ValidationException::withMessages(['submission' => 'Tunggu mahasiswa mengirim versi revisi sebelum memberi nilai.']);
            }

            $criteria = $submission->assignment->rubricCriteria;

            if ($criteria->count() < 2 || round((float) $criteria->sum('weight'), 2) !== 100.0 || $criteria->pluck('category')->unique()->count() !== 2) {
                throw ValidationException::withMessages(['scores' => 'Rubrik harus lengkap dan memiliki total bobot 100%.']);
            }

            $rules = ['feedback' => ['required', 'string', 'min:3', 'max:10000'], 'scores' => ['required', 'array:'.$criteria->pluck('id')->implode(',')]];

            foreach ($criteria as $criterion) {
                $rules['scores.'.$criterion->id] = ['required', 'numeric', 'between:0,'.$criterion->max_score, 'decimal:0,2'];
            }

            $data = Validator::make($input, $rules, ['scores.*.required' => 'Isi skor untuk setiap kriteria rubrik.', 'scores.*.between' => 'Skor harus berada dalam rentang rubrik.'])->validate();
            $snapshots = [];
            $weighted = ['hard_skill' => 0.0, 'soft_skill' => 0.0];
            $weights = ['hard_skill' => 0.0, 'soft_skill' => 0.0];

            foreach ($criteria as $criterion) {
                $score = (float) $data['scores'][$criterion->id];
                $snapshots[] = ['id' => $criterion->id, 'title' => $criterion->title, 'category' => $criterion->category, 'max_score' => $criterion->max_score, 'weight' => $criterion->weight, 'score' => $score];
                $weighted[$criterion->category] += $score / $criterion->max_score * $criterion->weight;
                $weights[$criterion->category] += $criterion->weight;
            }

            $assessment = $submission->assessment()->updateOrCreate([], [
                'assessor_id' => $user->id, 'scores' => $snapshots,
                'hard_skill_score' => round($weighted['hard_skill'] / $weights['hard_skill'] * 100, 2),
                'soft_skill_score' => round($weighted['soft_skill'] / $weights['soft_skill'] * 100, 2),
                'final_score' => round(array_sum($weighted), 2), 'feedback' => $data['feedback'], 'assessed_at' => now('UTC'),
            ]);
            $submission->update(['status' => 'graded', 'reviewer_id' => $user->id, 'reviewed_at' => now('UTC')]);

            return $assessment;
        });
    }

    public function requestRevision(User $user, int $submissionId, string $feedback): void
    {
        DB::transaction(function () use ($user, $submissionId, $feedback): void {
            $submission = $this->lockSubmission($user, $submissionId);
            Validator::make(['revision_feedback' => $feedback], ['revision_feedback' => ['required', 'string', 'min:5', 'max:10000']])->validate();

            if ($submission->status !== 'submitted') {
                throw ValidationException::withMessages(['submission' => 'Revisi hanya dapat diminta untuk laporan yang menunggu penilaian.']);
            }

            $submission->update(['status' => 'revision_requested', 'revision_feedback' => $feedback, 'reviewer_id' => $user->id, 'reviewed_at' => now('UTC')]);
        });
    }

    private function lockSubmission(User $user, int $submissionId): PracticumSubmission
    {
        $submission = PracticumSubmission::query()->forUser($user)->findOrFail($submissionId);
        Gate::forUser($user)->authorize('assess', $submission);
        PracticumAssignment::query()->lockForUpdate()->findOrFail($submission->practicum_assignment_id);
        $submission = PracticumSubmission::query()->latestAttempts()->lockForUpdate()->find($submissionId);

        if (! $submission) {
            throw ValidationException::withMessages(['submission' => 'Laporan ini memiliki versi lebih baru. Buka versi terbaru untuk menilai.']);
        }

        return $submission;
    }
}
