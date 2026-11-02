<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\InternshipAttendanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $internship_placement_id
 * @property CarbonImmutable $work_date
 * @property CarbonImmutable $checked_in_at
 * @property CarbonImmutable|null $checked_out_at
 * @property float $check_in_distance
 * @property float $check_in_accuracy
 * @property float|null $check_out_distance
 * @property float|null $check_out_accuracy
 * @property int $radius_meters
 * @property-read InternshipPlacement $placement
 */
#[Fillable(['internship_placement_id', 'work_date', 'checked_in_at', 'checked_out_at', 'check_in_latitude', 'check_in_longitude', 'check_in_accuracy', 'check_in_distance', 'check_out_latitude', 'check_out_longitude', 'check_out_accuracy', 'check_out_distance', 'radius_meters'])]
class InternshipAttendance extends Model
{
    /** @use HasFactory<InternshipAttendanceFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'work_date' => 'immutable_date', 'checked_in_at' => 'immutable_datetime', 'checked_out_at' => 'immutable_datetime',
            'check_in_distance' => 'float', 'check_in_accuracy' => 'float', 'check_out_distance' => 'float', 'check_out_accuracy' => 'float',
        ];
    }

    /** @return BelongsTo<InternshipPlacement, $this> */
    public function placement(): BelongsTo
    {
        return $this->belongsTo(InternshipPlacement::class, 'internship_placement_id');
    }

    public function elapsedMinutes(): int
    {
        return $this->checked_out_at ? max(0, (int) $this->checked_in_at->diffInMinutes($this->checked_out_at)) : 0;
    }
}
