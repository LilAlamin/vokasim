<?php

namespace App\Models;

use App\Enums\CertificateStatus;
use Database\Factories\CertificateReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $certificate_id
 * @property int|null $reviewer_id
 * @property CertificateStatus|null $from_status
 * @property CertificateStatus $to_status
 * @property string|null $feedback
 * @property-read Certificate $certificate
 * @property-read User|null $reviewer
 */
#[Fillable(['reviewer_id', 'from_status', 'to_status', 'feedback'])]
#[Hidden(['feedback'])]
class CertificateReview extends Model
{
    /** @use HasFactory<CertificateReviewFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['from_status' => CertificateStatus::class, 'to_status' => CertificateStatus::class, 'feedback' => 'encrypted'];
    }

    /** @return BelongsTo<Certificate, $this> */
    public function certificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
