<?php

namespace App\Models;

use Database\Factories\PartnerCompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $sector
 * @property string $address
 * @property string $city
 * @property string $province
 * @property float|null $latitude
 * @property float|null $longitude
 * @property string|null $contact_name
 * @property string|null $contact_email
 * @property string|null $contact_phone
 * @property int $internship_quota
 * @property bool $is_active
 */
#[Fillable(['name', 'sector', 'address', 'city', 'province', 'latitude', 'longitude', 'contact_name', 'contact_email', 'contact_phone', 'internship_quota', 'is_active'])]
class PartnerCompany extends Model
{
    /** @use HasFactory<PartnerCompanyFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['latitude' => 'float', 'longitude' => 'float', 'internship_quota' => 'integer', 'is_active' => 'boolean'];
    }

    /** @return HasMany<PartnerUnit, $this> */
    public function units(): HasMany
    {
        return $this->hasMany(PartnerUnit::class);
    }

    /** @return HasMany<PartnerAgreement, $this> */
    public function agreements(): HasMany
    {
        return $this->hasMany(PartnerAgreement::class);
    }

    /** @return HasMany<InternshipPlacement, $this> */
    public function placements(): HasMany
    {
        return $this->hasMany(InternshipPlacement::class);
    }
}
