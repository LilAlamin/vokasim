<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\InternshipLeaveRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $internship_placement_id
 * @property string $kind
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 * @property string $reason
 * @property string|null $evidence_path
 * @property string $status
 * @property int|null $reviewer_id
 * @property string|null $feedback
 * @property CarbonImmutable|null $reviewed_at
 * @property-read InternshipPlacement $placement
 * @property-read User|null $reviewer
 */
#[Fillable(['internship_placement_id', 'kind', 'starts_on', 'ends_on', 'reason', 'evidence_path', 'status', 'reviewer_id', 'feedback', 'reviewed_at'])]
#[Hidden(['evidence_path'])]
class InternshipLeaveRequest extends Model
{
    /** @use HasFactory<InternshipLeaveRequestFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['starts_on' => 'immutable_date', 'ends_on' => 'immutable_date', 'reviewed_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<InternshipPlacement, $this> */
    public function placement(): BelongsTo
    {
        return $this->belongsTo(InternshipPlacement::class, 'internship_placement_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /** @param Builder<InternshipLeaveRequest> $query */
    #[Scope]
    protected function forUser(Builder $query, User $user): void
    {
        $query->whereHas('placement', fn (Builder $placements) => $placements->forUser($user));
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            default => 'Menunggu mentor',
        };
    }
}
