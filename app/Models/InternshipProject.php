<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\InternshipProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $internship_placement_id
 * @property int $mentor_id
 * @property string $title
 * @property string $description
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $due_on
 * @property int $target_minutes
 * @property int $completed_minutes
 * @property string|null $feedback
 * @property-read InternshipPlacement $placement
 * @property-read User $mentor
 */
#[Fillable(['internship_placement_id', 'mentor_id', 'title', 'description', 'starts_on', 'due_on', 'target_minutes', 'completed_minutes', 'feedback'])]
class InternshipProject extends Model
{
    /** @use HasFactory<InternshipProjectFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['starts_on' => 'immutable_date', 'due_on' => 'immutable_date', 'target_minutes' => 'integer', 'completed_minutes' => 'integer'];
    }

    /** @return BelongsTo<InternshipPlacement, $this> */
    public function placement(): BelongsTo
    {
        return $this->belongsTo(InternshipPlacement::class, 'internship_placement_id');
    }

    /** @return BelongsTo<User, $this> */
    public function mentor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentor_id');
    }

    /** @param Builder<InternshipProject> $query */
    #[Scope]
    protected function forUser(Builder $query, User $user): void
    {
        $query->whereHas('placement', fn (Builder $placements) => $placements->forUser($user));
    }

    public function completionPercentage(): int
    {
        return $this->target_minutes > 0 ? min(100, (int) round($this->completed_minutes / $this->target_minutes * 100)) : 0;
    }
}
