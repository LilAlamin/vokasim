<?php

namespace App\Models;

use App\Enums\LogbookStatus;
use App\Enums\UserRole;
use Carbon\CarbonImmutable;
use Database\Factories\InternshipPlacementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $student_id
 * @property int $mentor_id
 * @property int $lecturer_id
 * @property int|null $partner_company_id
 * @property int|null $partner_unit_id
 * @property string $company_name
 * @property string $address
 * @property float $latitude
 * @property float $longitude
 * @property int $radius_meters
 * @property string $timezone
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 * @property int $target_hours
 * @property-read User $student
 * @property-read User $mentor
 * @property-read User $lecturer
 * @property-read PartnerCompany|null $company
 * @property-read PartnerUnit|null $unit
 */
#[Fillable(['student_id', 'mentor_id', 'lecturer_id', 'partner_company_id', 'partner_unit_id', 'company_name', 'address', 'latitude', 'longitude', 'radius_meters', 'timezone', 'starts_on', 'ends_on', 'target_hours'])]
class InternshipPlacement extends Model
{
    /** @use HasFactory<InternshipPlacementFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['starts_on' => 'immutable_date', 'ends_on' => 'immutable_date', 'latitude' => 'float', 'longitude' => 'float'];
    }

    /** @return BelongsTo<User, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /** @return BelongsTo<User, $this> */
    public function mentor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentor_id');
    }

    /** @return BelongsTo<User, $this> */
    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lecturer_id');
    }

    /** @return HasMany<InternshipAttendance, $this> */
    public function attendances(): HasMany
    {
        return $this->hasMany(InternshipAttendance::class);
    }

    /** @return HasMany<InternshipLogbook, $this> */
    public function logbooks(): HasMany
    {
        return $this->hasMany(InternshipLogbook::class);
    }

    /** @return BelongsTo<PartnerCompany, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(PartnerCompany::class, 'partner_company_id');
    }

    /** @return BelongsTo<PartnerUnit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(PartnerUnit::class, 'partner_unit_id');
    }

    /** @return HasMany<InternshipLeaveRequest, $this> */
    public function leaveRequests(): HasMany
    {
        return $this->hasMany(InternshipLeaveRequest::class);
    }

    /** @return HasMany<IndustryEvaluation, $this> */
    public function evaluations(): HasMany
    {
        return $this->hasMany(IndustryEvaluation::class);
    }

    /** @return HasMany<InternshipProject, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(InternshipProject::class);
    }

    /** @param Builder<InternshipPlacement> $query */
    #[Scope]
    protected function forUser(Builder $query, User $user): void
    {
        $column = match ($user->role) {
            UserRole::Mahasiswa => 'student_id',
            UserRole::MentorDudi => 'mentor_id',
            UserRole::Dosen => 'lecturer_id',
            UserRole::Admin => null,
        };

        if ($column !== null) {
            $query->where($column, $user->id);
        }
    }

    public function isActive(): bool
    {
        $date = CarbonImmutable::now($this->timezone)->toDateString();

        return $date >= $this->starts_on->toDateString() && $date <= $this->ends_on->toDateString();
    }

    public function verifiedHours(): float
    {
        return (float) $this->logbooks()->where('status', LogbookStatus::Verified)->sum('effective_minutes') / 60;
    }
}
