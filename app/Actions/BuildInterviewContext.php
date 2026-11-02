<?php

namespace App\Actions;

use App\Enums\CertificateStatus;
use App\Enums\LogbookStatus;
use App\Models\Certificate;
use App\Models\InternshipLogbook;
use App\Models\PortfolioProject;
use App\Models\User;
use Illuminate\Support\Str;

class BuildInterviewContext
{
    /** @return array<string, mixed> */
    public function handle(User $student): array
    {
        return [
            'study_program' => $student->study_program,
            'projects' => PortfolioProject::query()->where('student_id', $student->id)->latest('id')->limit(8)->get()
                ->map(fn (PortfolioProject $project): array => ['title' => $project->title, 'description' => Str::limit($project->description, 1200), 'skills' => $project->skills])->all(),
            'certificates' => Certificate::query()->where('student_id', $student->id)->where('status', CertificateStatus::Verified)
                ->whereDate('issued_on', '<=', today())->where(fn ($query) => $query->whereNull('expires_on')->orWhereDate('expires_on', '>=', today()))
                ->latest('id')->limit(10)->get()->map(fn (Certificate $certificate): array => ['title' => $certificate->title, 'skills' => $certificate->skills])->all(),
            'internship_activities' => InternshipLogbook::query()->whereHas('placement', fn ($query) => $query->where('student_id', $student->id))
                ->where('status', LogbookStatus::Verified)->latest('work_date')->limit(10)->get()
                ->map(fn (InternshipLogbook $logbook): array => ['activity' => Str::limit($logbook->description, 1200), 'hours' => $logbook->effective_minutes / 60])->all(),
        ];
    }
}
