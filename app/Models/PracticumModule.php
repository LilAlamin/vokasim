<?php

namespace App\Models;

use App\Enums\UserRole;
use Carbon\CarbonImmutable;
use Database\Factories\PracticumModuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $lecturer_id
 * @property string $code
 * @property string $title
 * @property string $study_program
 * @property int $semester
 * @property string $skill_area
 * @property string $description
 * @property string $timezone
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 * @property bool $published
 * @property-read User $lecturer
 */
#[Fillable(['lecturer_id', 'code', 'title', 'study_program', 'semester', 'skill_area', 'description', 'timezone', 'starts_on', 'ends_on', 'published'])]
class PracticumModule extends Model
{
    /** @use HasFactory<PracticumModuleFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['semester' => 'integer', 'published' => 'boolean', 'starts_on' => 'immutable_date', 'ends_on' => 'immutable_date'];
    }

    /** @return BelongsTo<User, $this> */
    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lecturer_id');
    }

    /** @return HasMany<PracticumEnrollment, $this> */
    public function enrollments(): HasMany
    {
        return $this->hasMany(PracticumEnrollment::class);
    }

    /** @return HasMany<PracticumMaterial, $this> */
    public function materials(): HasMany
    {
        return $this->hasMany(PracticumMaterial::class);
    }

    /** @return HasMany<PracticumSession, $this> */
    public function sessions(): HasMany
    {
        return $this->hasMany(PracticumSession::class);
    }

    /** @return HasMany<PracticumAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(PracticumAssignment::class);
    }

    /** @param Builder<PracticumModule> $query */
    #[Scope]
    protected function forUser(Builder $query, User $user): void
    {
        match ($user->role) {
            UserRole::Admin => null,
            UserRole::Dosen => $query->where('lecturer_id', $user->id),
            UserRole::Mahasiswa => $query->where('published', true)->whereHas('enrollments', fn (Builder $enrollments) => $enrollments->where('student_id', $user->id)->where('status', 'active')),
            UserRole::MentorDudi => $query->whereRaw('1 = 0'),
        };
    }
}
