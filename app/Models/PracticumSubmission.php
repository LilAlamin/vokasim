<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PracticumSubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $practicum_assignment_id
 * @property int $student_id
 * @property int $attempt
 * @property string $status
 * @property string|null $notes
 * @property string $file_path
 * @property string $original_name
 * @property string $mime_type
 * @property CarbonImmutable $submitted_at
 * @property bool $is_late
 * @property string|null $revision_feedback
 * @property int|null $reviewer_id
 * @property CarbonImmutable|null $reviewed_at
 * @property-read PracticumAssignment $assignment
 * @property-read User $student
 * @property-read User|null $reviewer
 * @property-read PracticumAssessment|null $assessment
 */
#[Fillable(['practicum_assignment_id', 'student_id', 'attempt', 'status', 'notes', 'file_path', 'original_name', 'mime_type', 'submitted_at', 'is_late', 'revision_feedback', 'reviewer_id', 'reviewed_at'])]
#[Hidden(['file_path'])]
class PracticumSubmission extends Model
{
    /** @use HasFactory<PracticumSubmissionFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['submitted_at' => 'immutable_datetime', 'reviewed_at' => 'immutable_datetime', 'is_late' => 'boolean'];
    }

    /** @return BelongsTo<PracticumAssignment, $this> */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(PracticumAssignment::class, 'practicum_assignment_id');
    }

    /** @return BelongsTo<User, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /** @return HasOne<PracticumAssessment, $this> */
    public function assessment(): HasOne
    {
        return $this->hasOne(PracticumAssessment::class);
    }

    /** @param Builder<PracticumSubmission> $query */
    #[Scope]
    protected function forUser(Builder $query, User $user): void
    {
        $query->whereHas('assignment.module', fn (Builder $modules) => $modules->forUser($user));

        if ($user->isMahasiswa()) {
            $query->where('student_id', $user->id);
        }
    }

    /** @param Builder<PracticumSubmission> $query */
    #[Scope]
    protected function latestAttempts(Builder $query): void
    {
        $query->whereNotExists(function (\Illuminate\Database\Query\Builder $newer): void {
            $newer->selectRaw('1')->from('practicum_submissions as newer')
                ->whereColumn('newer.practicum_assignment_id', 'practicum_submissions.practicum_assignment_id')
                ->whereColumn('newer.student_id', 'practicum_submissions.student_id')
                ->whereColumn('newer.attempt', '>', 'practicum_submissions.attempt');
        });
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'submitted' => 'Menunggu penilaian', 'revision_requested' => 'Perlu revisi', 'graded' => 'Sudah dinilai', default => $this->status,
        };
    }
}
