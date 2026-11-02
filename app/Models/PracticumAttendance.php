<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PracticumAttendanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $practicum_session_id
 * @property int $student_id
 * @property int $recorded_by
 * @property string $status
 * @property string|null $note
 * @property CarbonImmutable $recorded_at
 * @property-read PracticumSession $session
 * @property-read User $student
 */
#[Fillable(['practicum_session_id', 'student_id', 'recorded_by', 'status', 'note', 'recorded_at'])]
class PracticumAttendance extends Model
{
    /** @use HasFactory<PracticumAttendanceFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['recorded_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<PracticumSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(PracticumSession::class, 'practicum_session_id');
    }

    /** @return BelongsTo<User, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'present' => 'Hadir', 'late' => 'Terlambat', 'excused' => 'Izin / sakit', default => 'Tidak hadir',
        };
    }
}
