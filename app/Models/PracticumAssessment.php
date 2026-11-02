<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PracticumAssessmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $practicum_submission_id
 * @property int $assessor_id
 * @property array<int, array{id: int, title: string, category: string, max_score: float, weight: float, score: float}> $scores
 * @property float $hard_skill_score
 * @property float $soft_skill_score
 * @property float $final_score
 * @property string $feedback
 * @property CarbonImmutable $assessed_at
 * @property-read PracticumSubmission $submission
 * @property-read User $assessor
 */
#[Fillable(['practicum_submission_id', 'assessor_id', 'scores', 'hard_skill_score', 'soft_skill_score', 'final_score', 'feedback', 'assessed_at'])]
class PracticumAssessment extends Model
{
    /** @use HasFactory<PracticumAssessmentFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['scores' => 'array', 'hard_skill_score' => 'float', 'soft_skill_score' => 'float', 'final_score' => 'float', 'assessed_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<PracticumSubmission, $this> */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(PracticumSubmission::class, 'practicum_submission_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessor_id');
    }
}
