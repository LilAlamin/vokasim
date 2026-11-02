<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PracticumEnrollmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $practicum_module_id
 * @property int $student_id
 * @property string $status
 * @property CarbonImmutable $enrolled_at
 * @property-read PracticumModule $module
 * @property-read User $student
 */
#[Fillable(['practicum_module_id', 'student_id', 'status', 'enrolled_at'])]
class PracticumEnrollment extends Model
{
    /** @use HasFactory<PracticumEnrollmentFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['enrolled_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<PracticumModule, $this> */
    public function module(): BelongsTo
    {
        return $this->belongsTo(PracticumModule::class, 'practicum_module_id');
    }

    /** @return BelongsTo<User, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /** @param Builder<PracticumEnrollment> $query */
    #[Scope]
    protected function forUser(Builder $query, User $user): void
    {
        $query->whereHas('module', fn (Builder $modules) => $modules->forUser($user));

        if ($user->isMahasiswa()) {
            $query->where('student_id', $user->id);
        }
    }

    /** @return array{session_count: int, attended_count: int, attendance_percentage: int, assignment_count: int, submitted_count: int, graded_count: int, completion_percentage: int, average_grade: ?float, hard_skill_score: ?float, soft_skill_score: ?float} */
    public function progress(): array
    {
        $module = PracticumModule::query()->with([
            'sessions.attendances' => fn ($query) => $query->where('student_id', $this->student_id),
            'assignments.submissions' => fn ($query) => $query->where('student_id', $this->student_id)->with('assessment'),
        ])->findOrFail($this->practicum_module_id);

        return self::summarize($module, $this->student_id);
    }

    /** @return array{session_count: int, attended_count: int, attendance_percentage: int, assignment_count: int, submitted_count: int, graded_count: int, completion_percentage: int, average_grade: ?float, hard_skill_score: ?float, soft_skill_score: ?float} */
    public static function summarize(PracticumModule $module, int $studentId): array
    {
        $sessions = $module->sessions->filter(fn (PracticumSession $session): bool => ! $session->cancelled && $session->starts_at->isPast());
        $attended = $sessions->filter(fn (PracticumSession $session): bool => $session->attendances->contains(fn (PracticumAttendance $attendance): bool => $attendance->student_id === $studentId && in_array($attendance->status, ['present', 'late'], true)))->count();
        $submitted = 0;
        $graded = 0;
        $totalWeight = 0.0;
        $finalScore = 0.0;
        $hardSkillScore = 0.0;
        $softSkillScore = 0.0;

        foreach ($module->assignments as $assignment) {
            $submission = $assignment->submissions->where('student_id', $studentId)->sortByDesc('attempt')->first();

            if (! $submission) {
                continue;
            }

            $submitted++;

            if ($submission->status !== 'graded' || ! $submission->assessment) {
                continue;
            }

            $graded++;
            $totalWeight += $assignment->weight;
            $finalScore += $submission->assessment->final_score * $assignment->weight;
            $hardSkillScore += $submission->assessment->hard_skill_score * $assignment->weight;
            $softSkillScore += $submission->assessment->soft_skill_score * $assignment->weight;
        }

        $assignmentCount = $module->assignments->count();

        return [
            'session_count' => $sessions->count(), 'attended_count' => $attended,
            'attendance_percentage' => $sessions->isNotEmpty() ? (int) round($attended / $sessions->count() * 100) : 0,
            'assignment_count' => $assignmentCount, 'submitted_count' => $submitted, 'graded_count' => $graded,
            'completion_percentage' => $assignmentCount > 0 ? (int) round($graded / $assignmentCount * 100) : 0,
            'average_grade' => $totalWeight > 0 ? round($finalScore / $totalWeight, 2) : null,
            'hard_skill_score' => $totalWeight > 0 ? round($hardSkillScore / $totalWeight, 2) : null,
            'soft_skill_score' => $totalWeight > 0 ? round($softSkillScore / $totalWeight, 2) : null,
        ];
    }
}
