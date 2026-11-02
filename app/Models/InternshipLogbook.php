<?php

namespace App\Models;

use App\Enums\LogbookStatus;
use Carbon\CarbonImmutable;
use Database\Factories\InternshipLogbookFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $internship_placement_id
 * @property CarbonImmutable $work_date
 * @property string $description
 * @property int $effective_minutes
 * @property string|null $photo_path
 * @property LogbookStatus $status
 * @property CarbonImmutable|null $submitted_at
 * @property CarbonImmutable|null $mentor_approved_at
 * @property CarbonImmutable|null $verified_at
 * @property-read InternshipPlacement $placement
 * @property-read Collection<int, LogbookReview> $reviews
 */
#[Fillable(['internship_placement_id', 'work_date', 'description', 'effective_minutes', 'photo_path', 'status', 'submitted_at', 'mentor_approved_at', 'verified_at'])]
#[Hidden(['photo_path'])]
class InternshipLogbook extends Model
{
    /** @use HasFactory<InternshipLogbookFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['work_date' => 'immutable_date', 'status' => LogbookStatus::class, 'submitted_at' => 'immutable_datetime', 'mentor_approved_at' => 'immutable_datetime', 'verified_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<InternshipPlacement, $this> */
    public function placement(): BelongsTo
    {
        return $this->belongsTo(InternshipPlacement::class, 'internship_placement_id');
    }

    /** @return HasMany<LogbookReview, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(LogbookReview::class)->orderBy('id');
    }

    /** @param Builder<InternshipLogbook> $query */
    #[Scope]
    protected function forUser(Builder $query, User $user): void
    {
        $query->whereHas('placement', fn (Builder $placements) => $placements->forUser($user));
    }
}
