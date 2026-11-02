<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PracticumSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $practicum_module_id
 * @property string $title
 * @property string $room
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property bool $is_exam
 * @property bool $cancelled
 * @property-read PracticumModule $module
 */
#[Fillable(['practicum_module_id', 'title', 'room', 'starts_at', 'ends_at', 'is_exam', 'cancelled'])]
class PracticumSession extends Model
{
    /** @use HasFactory<PracticumSessionFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'is_exam' => 'boolean', 'cancelled' => 'boolean'];
    }

    /** @return BelongsTo<PracticumModule, $this> */
    public function module(): BelongsTo
    {
        return $this->belongsTo(PracticumModule::class, 'practicum_module_id');
    }

    /** @return HasMany<PracticumAttendance, $this> */
    public function attendances(): HasMany
    {
        return $this->hasMany(PracticumAttendance::class);
    }

    /** @param Builder<PracticumSession> $query */
    #[Scope]
    protected function forUser(Builder $query, User $user): void
    {
        $query->whereHas('module', fn (Builder $modules) => $modules->forUser($user));
    }

    public function isOpen(): bool
    {
        return ! $this->cancelled && CarbonImmutable::now('UTC')->betweenIncluded($this->starts_at, $this->ends_at);
    }
}
