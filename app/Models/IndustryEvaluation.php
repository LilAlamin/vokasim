<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\IndustryEvaluationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $internship_placement_id
 * @property int $mentor_id
 * @property string $period_type
 * @property CarbonImmutable $period_month
 * @property int $work_quality
 * @property int $tool_mastery
 * @property int $discipline
 * @property int $teamwork
 * @property int $professional_ethics
 * @property float $final_score
 * @property string $feedback
 * @property string $improvement_plan
 * @property int $revision
 * @property-read InternshipPlacement $placement
 * @property-read User $mentor
 */
#[Fillable(['internship_placement_id', 'mentor_id', 'period_type', 'period_month', 'work_quality', 'tool_mastery', 'discipline', 'teamwork', 'professional_ethics', 'final_score', 'feedback', 'improvement_plan', 'revision'])]
class IndustryEvaluation extends Model
{
    /** @use HasFactory<IndustryEvaluationFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['period_month' => 'immutable_date', 'final_score' => 'float', 'revision' => 'integer'];
    }

    /** @return array<string, array{label: string, description: string}> */
    public static function rubric(): array
    {
        return [
            'work_quality' => ['label' => 'Kualitas hasil kerja', 'description' => 'Ketelitian, penyelesaian tugas, dan kesesuaian hasil dengan standar kerja.'],
            'tool_mastery' => ['label' => 'Penguasaan alat industri', 'description' => 'Ketepatan penggunaan alat, prosedur kerja, dan penerapan K3.'],
            'discipline' => ['label' => 'Kedisiplinan', 'description' => 'Kehadiran, ketepatan waktu, dan kepatuhan terhadap komitmen kerja.'],
            'teamwork' => ['label' => 'Kerja sama & komunikasi', 'description' => 'Koordinasi, komunikasi profesional, dan kontribusi dalam tim.'],
            'professional_ethics' => ['label' => 'Etika profesional', 'description' => 'Integritas, tanggung jawab, inisiatif, dan penghormatan aturan perusahaan.'],
        ];
    }

    /** @return array<int, string> */
    public static function scoreLabels(): array
    {
        return [1 => 'Perlu pendampingan penuh', 2 => 'Perlu banyak arahan', 3 => 'Memenuhi standar', 4 => 'Mandiri dan konsisten', 5 => 'Melebihi standar'];
    }

    /** @return BelongsTo<InternshipPlacement, $this> */
    public function placement(): BelongsTo
    {
        return $this->belongsTo(InternshipPlacement::class, 'internship_placement_id');
    }

    /** @return BelongsTo<User, $this> */
    public function mentor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentor_id');
    }

    /** @param Builder<IndustryEvaluation> $query */
    #[Scope]
    protected function forUser(Builder $query, User $user): void
    {
        $query->whereHas('placement', fn (Builder $placements) => $placements->forUser($user));
    }
}
