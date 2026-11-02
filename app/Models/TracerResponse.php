<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\TracerResponseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $graduation_year
 * @property CarbonImmutable $graduated_on
 * @property string|null $study_program
 * @property string $employment_status
 * @property string|null $employer_name
 * @property string|null $job_title
 * @property int|null $partner_company_id
 * @property CarbonImmutable|null $first_job_started_on
 * @property int|null $wait_months
 * @property int|null $first_monthly_salary
 * @property string|null $field_alignment
 * @property array<int, string>|null $skill_gaps
 * @property string|null $curriculum_feedback
 * @property CarbonImmutable $consented_at
 * @property-read User $user
 * @property-read PartnerCompany|null $company
 */
#[Fillable(['user_id', 'graduation_year', 'graduated_on', 'study_program', 'employment_status', 'employer_name', 'job_title', 'partner_company_id', 'first_job_started_on', 'wait_months', 'first_monthly_salary', 'field_alignment', 'skill_gaps', 'curriculum_feedback', 'consented_at'])]
class TracerResponse extends Model
{
    /** @use HasFactory<TracerResponseFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['graduation_year' => 'integer', 'graduated_on' => 'immutable_date', 'first_job_started_on' => 'immutable_date', 'wait_months' => 'integer', 'first_monthly_salary' => 'integer', 'skill_gaps' => 'array', 'consented_at' => 'immutable_datetime'];
    }

    /** @return array<string, string> */
    public static function employmentStatuses(): array
    {
        return ['employed' => 'Bekerja', 'entrepreneur' => 'Berwirausaha', 'seeking' => 'Mencari pekerjaan', 'studying' => 'Melanjutkan studi', 'not_seeking' => 'Belum mencari pekerjaan'];
    }

    /** @return array<string, string> */
    public static function alignmentLabels(): array
    {
        return ['aligned' => 'Sesuai bidang studi', 'partial' => 'Sebagian sesuai', 'unrelated' => 'Tidak sesuai', 'unknown' => 'Belum dapat menilai'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<PartnerCompany, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(PartnerCompany::class, 'partner_company_id');
    }
}
