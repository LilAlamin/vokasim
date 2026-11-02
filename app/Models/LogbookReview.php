<?php

namespace App\Models;

use App\Enums\LogbookStatus;
use Carbon\CarbonImmutable;
use Database\Factories\LogbookReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property LogbookStatus $from_status
 * @property LogbookStatus $to_status
 * @property string|null $feedback
 * @property CarbonImmutable $created_at
 * @property-read User $reviewer
 */
#[Fillable(['internship_logbook_id', 'reviewer_id', 'from_status', 'to_status', 'feedback'])]
class LogbookReview extends Model
{
    /** @use HasFactory<LogbookReviewFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['from_status' => LogbookStatus::class, 'to_status' => LogbookStatus::class, 'created_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<InternshipLogbook, $this> */
    public function logbook(): BelongsTo
    {
        return $this->belongsTo(InternshipLogbook::class, 'internship_logbook_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
