<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PracticumAssignmentFactory;
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
 * @property string $instructions
 * @property CarbonImmutable $due_at
 * @property float $weight
 * @property bool $allow_late
 * @property-read PracticumModule $module
 */
#[Fillable(['practicum_module_id', 'title', 'instructions', 'due_at', 'weight', 'allow_late'])]
class PracticumAssignment extends Model
{
    /** @use HasFactory<PracticumAssignmentFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['due_at' => 'immutable_datetime', 'weight' => 'float', 'allow_late' => 'boolean'];
    }

    /** @return BelongsTo<PracticumModule, $this> */
    public function module(): BelongsTo
    {
        return $this->belongsTo(PracticumModule::class, 'practicum_module_id');
    }

    /** @return HasMany<PracticumRubricCriterion, $this> */
    public function rubricCriteria(): HasMany
    {
        return $this->hasMany(PracticumRubricCriterion::class)->orderBy('position');
    }

    /** @return HasMany<PracticumSubmission, $this> */
    public function submissions(): HasMany
    {
        return $this->hasMany(PracticumSubmission::class);
    }

    /** @param Builder<PracticumAssignment> $query */
    #[Scope]
    protected function forUser(Builder $query, User $user): void
    {
        $query->whereHas('module', fn (Builder $modules) => $modules->forUser($user));
    }
}
