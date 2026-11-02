<?php

namespace App\Observers;

use App\Actions\NotifyVokasimActivity;
use App\Models\IndustryEvaluation;
use App\Models\InternshipLeaveRequest;
use App\Models\InterviewSession;
use App\Models\PracticumAssessment;
use App\Models\PracticumSubmission;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Database\Eloquent\Model;

class VokasimActivityObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(private NotifyVokasimActivity $notify) {}

    public function created(Model $record): void
    {
        $this->notify->handle($record);
    }

    public function updated(Model $record): void
    {
        $fields = match (true) {
            $record instanceof InternshipLeaveRequest => ['status'],
            $record instanceof IndustryEvaluation => ['revision'],
            $record instanceof PracticumSubmission => ['status'],
            $record instanceof PracticumAssessment => ['scores', 'feedback', 'assessed_at'],
            $record instanceof InterviewSession => ['status'],
            default => [],
        };

        if ($fields !== [] && $record->wasChanged($fields)) {
            $this->notify->handle($record);
        }
    }
}
