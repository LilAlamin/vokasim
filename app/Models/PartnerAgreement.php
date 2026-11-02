<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PartnerAgreementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $partner_company_id
 * @property string $reference_number
 * @property string $kind
 * @property CarbonImmutable $signed_on
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $expires_on
 * @property string|null $document_path
 * @property string|null $notes
 * @property bool $is_active
 * @property-read PartnerCompany $company
 */
#[Fillable(['partner_company_id', 'reference_number', 'kind', 'signed_on', 'starts_on', 'expires_on', 'document_path', 'notes', 'is_active'])]
#[Hidden(['document_path'])]
class PartnerAgreement extends Model
{
    /** @use HasFactory<PartnerAgreementFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['signed_on' => 'immutable_date', 'starts_on' => 'immutable_date', 'expires_on' => 'immutable_date', 'is_active' => 'boolean'];
    }

    /** @return BelongsTo<PartnerCompany, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(PartnerCompany::class, 'partner_company_id');
    }

    /** @param Builder<PartnerAgreement> $query */
    #[Scope]
    protected function expiringSoon(Builder $query): void
    {
        $query->where('is_active', true)->whereDate('starts_on', '<=', today())
            ->whereDate('expires_on', '>=', today())->whereDate('expires_on', '<=', today()->addDays(30));
    }

    public function statusLabel(): string
    {
        if (! $this->is_active) {
            return 'Dihentikan';
        }

        if ($this->expires_on->toDateString() < today()->toDateString()) {
            return 'Kedaluwarsa';
        }

        if ($this->starts_on->toDateString() > today()->toDateString()) {
            return 'Belum berlaku';
        }

        return $this->expires_on->toDateString() <= today()->addDays(30)->toDateString() ? 'Berakhir ≤ 30 hari' : 'Aktif';
    }
}
