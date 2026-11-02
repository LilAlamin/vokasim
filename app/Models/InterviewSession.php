<?php

namespace App\Models;

use App\Enums\InterviewStatus;
use Carbon\CarbonImmutable;
use Database\Factories\InterviewSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $public_id
 * @property int $student_id
 * @property string $target_position
 * @property string $difficulty
 * @property string $interview_type
 * @property string $interaction_mode
 * @property InterviewStatus $status
 * @property int $question_count
 * @property int $answer_seconds
 * @property bool $use_context
 * @property array<string, mixed> $context_snapshot
 * @property CarbonImmutable $consented_at
 * @property string $ai_model
 * @property float|null $overall_score
 * @property float|null $star_score
 * @property float|null $technical_score
 * @property float|null $communication_score
 * @property array<string, mixed>|null $evaluation
 * @property string|null $failure_stage
 * @property string|null $error_message
 * @property int $retry_count
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable $created_at
 * @property-read User $student
 * @property-read Collection<int, InterviewQuestion> $questions
 * @property-read Collection<int, InterviewNote> $notes
 */
#[Fillable(['public_id', 'student_id', 'target_position', 'difficulty', 'interview_type', 'interaction_mode', 'status', 'question_count', 'answer_seconds', 'use_context', 'context_snapshot', 'consented_at', 'ai_model', 'overall_score', 'star_score', 'technical_score', 'communication_score', 'evaluation', 'failure_stage', 'error_message', 'retry_count', 'started_at', 'completed_at'])]
#[Hidden(['context_snapshot', 'evaluation', 'error_message'])]
class InterviewSession extends Model
{
    /** @use HasFactory<InterviewSessionFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (InterviewSession $session): void {
            $session->public_id ??= (string) Str::uuid();
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => InterviewStatus::class, 'use_context' => 'boolean',
            'context_snapshot' => 'encrypted:array', 'evaluation' => 'encrypted:array',
            'consented_at' => 'immutable_datetime', 'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime',
            'overall_score' => 'float', 'star_score' => 'float', 'technical_score' => 'float', 'communication_score' => 'float',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @return BelongsTo<User, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /** @return HasMany<InterviewQuestion, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(InterviewQuestion::class)->orderBy('sequence');
    }

    /** @return HasMany<InterviewNote, $this> */
    public function notes(): HasMany
    {
        return $this->hasMany(InterviewNote::class)->latest('id');
    }

    /** @param Builder<InterviewSession> $query */
    #[Scope]
    protected function forUser(Builder $query, User $user): void
    {
        if ($user->isMahasiswa()) {
            $query->where('student_id', $user->id);
        } elseif ($user->isDosen()) {
            $query->where(function (Builder $students) use ($user): void {
                $students->whereIn('student_id', InternshipPlacement::query()->where('lecturer_id', $user->id)->select('student_id'))
                    ->orWhereIn('student_id', PracticumEnrollment::query()->where('status', 'active')
                        ->whereIn('practicum_module_id', PracticumModule::query()->where('lecturer_id', $user->id)->select('id'))->select('student_id'));
            });
        } else {
            $query->whereRaw('1 = 0');
        }
    }

    public function currentQuestion(): ?InterviewQuestion
    {
        return $this->questions()->whereNull('answered_at')->first();
    }
}
