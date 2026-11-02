<?php

namespace App\Models;

use Database\Factories\InterviewNoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $interview_session_id
 * @property int|null $interview_question_id
 * @property int $reviewer_id
 * @property string $body
 * @property-read User $reviewer
 * @property-read InterviewSession $session
 * @property-read InterviewQuestion|null $question
 */
#[Fillable(['interview_session_id', 'interview_question_id', 'reviewer_id', 'body'])]
#[Hidden(['body'])]
class InterviewNote extends Model
{
    /** @use HasFactory<InterviewNoteFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['body' => 'encrypted'];
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /** @return BelongsTo<InterviewSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(InterviewSession::class, 'interview_session_id');
    }

    /** @return BelongsTo<InterviewQuestion, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(InterviewQuestion::class, 'interview_question_id');
    }
}
