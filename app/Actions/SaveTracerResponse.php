<?php

namespace App\Actions;

use App\Models\TracerResponse;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaveTracerResponse
{
    /** @param array<string, mixed> $input */
    public function handle(User $user, array $input): TracerResponse
    {
        Gate::forUser($user)->authorize('create', TracerResponse::class);
        $working = in_array($input['employment_status'] ?? null, ['employed', 'entrepreneur'], true);
        $data = Validator::make($input, [
            'graduation_year' => ['required', 'integer', 'between:1950,'.now()->year],
            'graduated_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'employment_status' => ['required', Rule::in(array_keys(TracerResponse::employmentStatuses()))],
            'employer_name' => ['exclude_unless:employment_status,employed,entrepreneur', 'required', 'string', 'max:255'],
            'job_title' => ['exclude_unless:employment_status,employed,entrepreneur', 'required', 'string', 'max:255'],
            'partner_company_id' => ['exclude_unless:employment_status,employed,entrepreneur', 'nullable', 'integer', Rule::exists('partner_companies', 'id')],
            'first_job_started_on' => ['nullable', Rule::requiredIf($working), 'date_format:Y-m-d', 'after_or_equal:1950-01-01', 'before_or_equal:today'],
            'first_monthly_salary' => ['nullable', 'integer', 'between:0,1000000000', Rule::prohibitedIf(empty($input['first_job_started_on']))],
            'field_alignment' => ['exclude_unless:employment_status,employed,entrepreneur', 'required', Rule::in(array_keys(TracerResponse::alignmentLabels()))],
            'skill_gaps' => ['nullable', 'array', 'max:15'],
            'skill_gaps.*' => ['required', 'string', 'max:80'],
            'curriculum_feedback' => ['nullable', 'string', 'max:3000'],
            'consent' => ['accepted'],
        ], [
            'consent.accepted' => 'Setujui penggunaan jawaban untuk analisis institusi sebelum mengirim.',
            'first_job_started_on.required' => 'Isi tanggal pertama mulai bekerja atau berwirausaha.',
            'first_monthly_salary.prohibited' => 'Isi tanggal pekerjaan pertama sebelum mencatat gaji pertama.',
            'graduated_on.before_or_equal' => 'Survei alumni hanya dapat diisi setelah lulus.',
        ])->validate();

        $graduated = CarbonImmutable::parse($data['graduated_on']);

        if ($graduated->year !== (int) $data['graduation_year']) {
            throw ValidationException::withMessages(['graduation_year' => 'Tahun kelulusan harus sama dengan tahun pada tanggal lulus.']);
        }

        $firstJob = empty($data['first_job_started_on']) ? null : CarbonImmutable::parse($data['first_job_started_on']);
        $data['wait_months'] = $firstJob ? max(0, (int) $graduated->diffInMonths($firstJob, false)) : null;
        $data['first_job_started_on'] = $firstJob?->toDateString();
        $data['first_monthly_salary'] = ($data['first_monthly_salary'] ?? '') === '' ? null : $data['first_monthly_salary'];
        $data['partner_company_id'] = ($data['partner_company_id'] ?? '') === '' ? null : $data['partner_company_id'];
        $data['skill_gaps'] = array_values(array_unique(array_filter(array_map(
            fn (string $skill): string => mb_strtolower(trim($skill)),
            $data['skill_gaps'] ?? [],
        ))));

        foreach (['employer_name', 'job_title', 'field_alignment'] as $field) {
            $data[$field] = $data[$field] ?? null;
        }

        unset($data['consent']);

        return DB::transaction(function () use ($user, $data): TracerResponse {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            Gate::forUser($user)->authorize('create', TracerResponse::class);

            return TracerResponse::query()->updateOrCreate(['user_id' => $user->id], [
                ...$data,
                'study_program' => $user->study_program,
                'consented_at' => now(),
            ]);
        });
    }
}
