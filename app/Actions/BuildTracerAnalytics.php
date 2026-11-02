<?php

namespace App\Actions;

use App\Models\TracerResponse;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class BuildTracerAnalytics
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{total_responses: int, working_count: int, absorption_percentage: float|null, average_wait_months: float|null, wait_sample: int, average_first_salary: float|null, salary_sample: int, alignment_percentage: float|null, alignment_sample: int, status_counts: array<string, int>, graduation_year_counts: array<int, int>, skill_gaps: array<string, int>}
     */
    public function handle(User $user, array $filters = []): array
    {
        Gate::forUser($user)->authorize('viewAny', TracerResponse::class);
        $data = Validator::make($filters, [
            'graduation_year' => ['nullable', 'integer', 'between:1950,'.now()->year],
            'study_program' => ['nullable', 'string', 'max:255'],
        ])->validate();
        $responses = TracerResponse::query()
            ->when(! empty($data['graduation_year']), fn ($query) => $query->where('graduation_year', $data['graduation_year']))
            ->when(! empty($data['study_program']), fn ($query) => $query->where('study_program', $data['study_program']));
        $total = (clone $responses)->count();
        $statusCounts = array_fill_keys(array_keys(TracerResponse::employmentStatuses()), 0);

        foreach ((clone $responses)->selectRaw('employment_status, COUNT(*) as response_count')->groupBy('employment_status')->toBase()->get() as $group) {
            $statusCounts[$group->employment_status] = (int) $group->response_count;
        }

        $workingCount = $statusCounts['employed'] + $statusCounts['entrepreneur'];
        $waitSample = (clone $responses)->whereNotNull('wait_months')->count();
        $salarySample = (clone $responses)->whereNotNull('first_monthly_salary')->count();
        $alignment = (clone $responses)->whereIn('employment_status', ['employed', 'entrepreneur'])
            ->whereIn('field_alignment', ['aligned', 'partial', 'unrelated']);
        $alignmentSample = (clone $alignment)->count();
        $skillCounts = [];

        foreach ((clone $responses)->select(['id', 'skill_gaps'])->lazyById(250) as $response) {
            foreach ($response->skill_gaps ?? [] as $skill) {
                $skillCounts[$skill] = ($skillCounts[$skill] ?? 0) + 1;
            }
        }

        arsort($skillCounts);
        $years = [];
        foreach ((clone $responses)->selectRaw('graduation_year, COUNT(*) as response_count')->groupBy('graduation_year')->orderBy('graduation_year')->toBase()->get() as $group) {
            $years[(int) $group->graduation_year] = (int) $group->response_count;
        }

        return [
            'total_responses' => $total,
            'working_count' => $workingCount,
            'absorption_percentage' => $total > 0 ? round($workingCount / $total * 100, 1) : null,
            'average_wait_months' => $waitSample > 0 ? round((float) (clone $responses)->avg('wait_months'), 1) : null,
            'wait_sample' => $waitSample,
            'average_first_salary' => $salarySample > 0 ? round((float) (clone $responses)->avg('first_monthly_salary'), 0) : null,
            'salary_sample' => $salarySample,
            'alignment_percentage' => $alignmentSample > 0 ? round((clone $alignment)->where('field_alignment', 'aligned')->count() / $alignmentSample * 100, 1) : null,
            'alignment_sample' => $alignmentSample,
            'status_counts' => $statusCounts,
            'graduation_year_counts' => $years,
            'skill_gaps' => array_slice($skillCounts, 0, 10, true),
        ];
    }
}
