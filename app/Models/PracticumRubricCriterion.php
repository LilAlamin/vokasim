<?php

namespace App\Models;

use Database\Factories\PracticumRubricCriterionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $practicum_assignment_id
 * @property string $title
 * @property string $category
 * @property float $max_score
 * @property float $weight
 * @property int $position
 * @property-read PracticumAssignment $assignment
 */
#[Fillable(['practicum_assignment_id', 'title', 'category', 'max_score', 'weight', 'position'])]
class PracticumRubricCriterion extends Model
{
    /** @use HasFactory<PracticumRubricCriterionFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['max_score' => 'float', 'weight' => 'float'];
    }

    /** @return BelongsTo<PracticumAssignment, $this> */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(PracticumAssignment::class, 'practicum_assignment_id');
    }
}
