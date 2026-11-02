<?php

namespace App\Actions;

use App\Models\InternshipPlacement;
use App\Models\PartnerCompany;
use App\Models\PartnerUnit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SavePartnerUnit
{
    public function __construct(private CheckPartnerCapacity $capacity) {}

    /** @param array<string, mixed> $input */
    public function handle(User $user, int $companyId, array $input, ?int $unitId = null): PartnerUnit
    {
        Gate::forUser($user)->authorize('create', PartnerUnit::class);

        return DB::transaction(function () use ($user, $companyId, $input, $unitId): PartnerUnit {
            $company = PartnerCompany::query()->lockForUpdate()->findOrFail($companyId);
            $unit = $unitId ? $company->units()->lockForUpdate()->findOrFail($unitId) : new PartnerUnit;

            if ($unit->exists) {
                Gate::forUser($user)->authorize('update', $unit);
            }

            $data = Validator::make($input, [
                'name' => ['required', 'string', 'max:255', Rule::unique('partner_units')->where('partner_company_id', $company->id)->ignore($unit->id)],
                'address' => ['required', 'string', 'max:500'],
                'latitude' => ['required', 'numeric', 'between:-90,90'],
                'longitude' => ['required', 'numeric', 'between:-180,180'],
                'radius_meters' => ['required', 'integer', 'between:10,1000'],
                'timezone' => ['required', Rule::in(['Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura'])],
                'internship_quota' => ['required', 'integer', 'between:0,10000'],
                'is_active' => ['required', 'boolean'],
            ], ['required' => ':attribute wajib diisi.', 'name.unique' => 'Nama unit sudah digunakan pada perusahaan ini.'])->validate();

            $otherAllocation = (int) $company->units()->where('is_active', true)
                ->when($unit->exists, fn ($query) => $query->whereKeyNot($unit->id))->sum('internship_quota');

            if ($data['is_active'] && $otherAllocation + (int) $data['internship_quota'] > $company->internship_quota) {
                throw ValidationException::withMessages(['internship_quota' => 'Total alokasi unit aktif melebihi kuota perusahaan.']);
            }

            if ($unit->exists && $this->capacity->peak(InternshipPlacement::query()->where('partner_unit_id', $unit->id), today()->toDateString()) > (int) $data['internship_quota']) {
                throw ValidationException::withMessages(['internship_quota' => 'Kuota unit masih digunakan oleh penempatan yang dijadwalkan.']);
            }

            $unit->fill([...$data, 'partner_company_id' => $company->id])->save();

            return $unit;
        });
    }
}
