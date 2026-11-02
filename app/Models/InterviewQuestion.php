<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\InterviewQuestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $interview_session_id
 * @property int $sequence
 * @property string $category
 * @property string $prompt
 * @property string|null $answer_text
 * @property string|null $audio_path
 * @property string|null $video_path
 * @property float|null $recorded_seconds
 * @property CarbonImmutable|null $opened_at
 * @property CarbonImmutable|null $answered_at
 * @property bool $timed_out
 * @property array<string, mixed>|null $evaluation
 * @property-read InterviewSession $session
 */
#[Fillable(['interview_session_id', 'sequence', 'category', 'prompt', 'answer_text', 'audio_path', 'video_path', 'recorded_seconds', 'opened_at', 'answered_at', 'timed_out', 'evaluation'])]
#[Hidden(['answer_text', 'audio_path', 'video_path', 'evaluation'])]
class InterviewQuestion extends Model
{
    /** @use HasFactory<InterviewQuestionFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['answer_text' => 'encrypted', 'evaluation' => 'encrypted:array', 'recorded_seconds' => 'float', 'opened_at' => 'immutable_datetime', 'answered_at' => 'immutable_datetime', 'timed_out' => 'boolean'];
    }

    /** @return BelongsTo<InterviewSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(InterviewSession::class, 'interview_session_id');
    }
}
