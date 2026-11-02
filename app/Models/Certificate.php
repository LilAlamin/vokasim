<?php

namespace App\Models;

use App\Concerns\ScopesPortfolioAccess;
use App\Enums\CertificateStatus;
use Carbon\CarbonImmutable;
use Database\Factories\CertificateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $student_id
 * @property string $verification_token
 * @property string $holder_name
 * @property string $title
 * @property string $issuer
 * @property string|null $credential_number
 * @property list<string> $skills
 * @property CarbonImmutable $issued_on
 * @property CarbonImmutable|null $expires_on
 * @property CertificateStatus $status
 * @property string $file_path
 * @property string $file_name
 * @property string $file_mime
 * @property int $file_size
 * @property CarbonImmutable $public_consent_at
 * @property int|null $verified_by
 * @property CarbonImmutable|null $verified_at
 * @property int|null $revoked_by
 * @property CarbonImmutable|null $revoked_at
 * @property-read User $student
 * @property-read User|null $verifier
 * @property-read User|null $revoker
 * @property-read Collection<int, CertificateReview> $reviews
 */
#[Fillable(['student_id', 'holder_name', 'title', 'issuer', 'credential_number', 'skills', 'issued_on', 'expires_on', 'status', 'file_path', 'file_name', 'file_mime', 'file_size', 'public_consent_at', 'verified_by', 'verified_at', 'revoked_by', 'revoked_at'])]
#[Hidden(['credential_number', 'file_path'])]
class Certificate extends Model
{
    /** @use HasFactory<CertificateFactory> */
    use HasFactory, HasUuids, ScopesPortfolioAccess;

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['verification_token'];
    }

    public function newUniqueId(): string
    {
        return (string) Str::uuid();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'credential_number' => 'encrypted',
            'skills' => 'array',
            'status' => CertificateStatus::class,
            'issued_on' => 'immutable_date',
            'expires_on' => 'immutable_date',
            'public_consent_at' => 'immutable_datetime',
            'verified_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /** @return BelongsTo<User, $this> */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /** @return BelongsTo<User, $this> */
    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    /** @return HasMany<CertificateReview, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(CertificateReview::class)->orderByDesc('id');
    }

    public function isExpired(): bool
    {
        return $this->expires_on !== null && $this->expires_on->toDateString() < CarbonImmutable::today()->toDateString();
    }

    public function isPubliclyVerifiable(): bool
    {
        return $this->verified_at !== null && in_array($this->status, [CertificateStatus::Verified, CertificateStatus::Revoked], true);
    }

    public function publicStatus(): string
    {
        return $this->status === CertificateStatus::Verified && $this->isExpired() ? 'Kedaluwarsa' : $this->status->label();
    }
}
