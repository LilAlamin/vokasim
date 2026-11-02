<?php

namespace App\Actions;

use App\Enums\UserRole;
use App\Models\InternshipPlacement;
use App\Models\PartnerCompany;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaveInternshipPlacement
{
    public function __construct(private CheckPartnerCapacity $capacity) {}

    /** @param array<string, mixed> $input */
    public function handle(User $user, array $input, ?int $placementId = null): InternshipPlacement
    {
        Gate::forUser($user)->authorize('create', InternshipPlacement::class);

        $data = Validator::make($input, [
            'student_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', UserRole::Mahasiswa->value)],
            'mentor_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', UserRole::MentorDudi->value)],
            'lecturer_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', UserRole::Dosen->value)],
            'partner_company_id' => ['nullable', 'integer', Rule::exists('partner_companies', 'id')],
            'partner_unit_id' => ['nullable', 'integer', Rule::exists('partner_units', 'id')],
            'company_name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:500'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_meters' => ['required', 'integer', 'between:10,1000'],
            'timezone' => ['required', Rule::in(['Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura'])],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'target_hours' => ['required', 'integer', 'between:1,5000'],
        ], [
            'required' => ':attribute wajib diisi.',
            '*.exists' => 'Pilih pengguna dengan role yang sesuai.',
            'ends_on.after_or_equal' => 'Tanggal selesai harus sama atau setelah tanggal mulai.',
            'radius_meters.between' => 'Radius lokasi harus antara 10 dan 1.000 meter.',
        ])->validate();

        $data['partner_company_id'] = empty($data['partner_company_id']) ? null : (int) $data['partner_company_id'];
        $data['partner_unit_id'] = empty($data['partner_unit_id']) ? null : (int) $data['partner_unit_id'];

        return DB::transaction(function () use ($data, $placementId): InternshipPlacement {
            User::query()->whereKey($data['student_id'])->lockForUpdate()->firstOrFail();
            $placement = $placementId ? InternshipPlacement::query()->lockForUpdate()->findOrFail($placementId) : new InternshipPlacement;

            if ($placement->exists && ($placement->attendances()->exists() || $placement->logbooks()->exists() || $placement->leaveRequests()->exists() || $placement->evaluations()->exists() || $placement->projects()->exists())) {
                throw ValidationException::withMessages(['placement' => 'Penempatan yang sudah memiliki presensi, logbook, izin, evaluasi, atau proyek tidak dapat diubah.']);
            }

            $overlaps = InternshipPlacement::query()->where('student_id', $data['student_id'])
                ->when($placementId, fn ($query) => $query->whereKeyNot($placementId))
                ->whereDate('starts_on', '<=', $data['ends_on'])->whereDate('ends_on', '>=', $data['starts_on'])->exists();

            if ($overlaps) {
                throw ValidationException::withMessages(['student_id' => 'Mahasiswa sudah memiliki penempatan pada periode tersebut.']);
            }

            if ($data['partner_unit_id'] && ! $data['partner_company_id']) {
                throw ValidationException::withMessages(['partner_company_id' => 'Pilih perusahaan pemilik unit kerja.']);
            }

            if ($data['partner_company_id']) {
                $company = PartnerCompany::query()->lockForUpdate()->findOrFail($data['partner_company_id']);

                if (! $company->is_active) {
                    throw ValidationException::withMessages(['partner_company_id' => 'Perusahaan tidak aktif untuk penempatan baru.']);
                }

                $unit = $data['partner_unit_id'] ? $company->units()->lockForUpdate()->find($data['partner_unit_id']) : null;

                if ($data['partner_unit_id'] && (! $unit || ! $unit->is_active)) {
                    throw ValidationException::withMessages(['partner_unit_id' => 'Pilih unit aktif milik perusahaan yang dipilih.']);
                }

                $this->capacity->assertAvailable($company, $unit, $data['starts_on'], $data['ends_on'], $placementId);
                $data['company_name'] = $company->name;

                if ($unit) {
                    $data = [...$data, ...$unit->only(['address', 'latitude', 'longitude', 'radius_meters', 'timezone'])];
                }
            }

            $placement->fill($data)->save();

            return $placement;
        });
    }
}
