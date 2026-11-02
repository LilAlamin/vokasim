<?php

namespace App\Models;

use Database\Factories\PartnerUnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $partner_company_id
 * @property string $name
 * @property string $address
 * @property float $latitude
 * @property float $longitude
 * @property int $radius_meters
 * @property string $timezone
 * @property int $internship_quota
 * @property bool $is_active
 * @property-read PartnerCompany $company
 */
#[Fillable(['partner_company_id', 'name', 'address', 'latitude', 'longitude', 'radius_meters', 'timezone', 'internship_quota', 'is_active'])]
class PartnerUnit extends Model
{
    /** @use HasFactory<PartnerUnitFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['latitude' => 'float', 'longitude' => 'float', 'radius_meters' => 'integer', 'internship_quota' => 'integer', 'is_active' => 'boolean'];
    }

    /** @return BelongsTo<PartnerCompany, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(PartnerCompany::class, 'partner_company_id');
    }

    /** @return HasMany<InternshipPlacement, $this> */
    public function placements(): HasMany
    {
        return $this->hasMany(InternshipPlacement::class);
    }
}
