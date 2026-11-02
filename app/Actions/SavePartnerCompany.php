<?php

namespace App\Actions;

use App\Models\InternshipPlacement;
use App\Models\PartnerCompany;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SavePartnerCompany
{
    public function __construct(private CheckPartnerCapacity $capacity) {}

    /** @param array<string, mixed> $input */
    public function handle(User $user, array $input, ?int $companyId = null): PartnerCompany
    {
        Gate::forUser($user)->authorize('create', PartnerCompany::class);
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'sector' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:500'],
            'city' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'internship_quota' => ['required', 'integer', 'between:0,10000'],
            'is_active' => ['required', 'boolean'],
        ], ['required' => ':attribute wajib diisi.', 'required_with' => 'Lengkapi kedua koordinat lokasi.'])->validate();

        foreach (['latitude', 'longitude', 'contact_name', 'contact_email', 'contact_phone'] as $field) {
            $data[$field] = ($data[$field] ?? '') === '' ? null : $data[$field];
        }

        return DB::transaction(function () use ($user, $data, $companyId): PartnerCompany {
            $company = $companyId ? PartnerCompany::query()->lockForUpdate()->findOrFail($companyId) : new PartnerCompany;

            if ($company->exists) {
                Gate::forUser($user)->authorize('update', $company);
                $allocated = (int) $company->units()->where('is_active', true)->sum('internship_quota');
                $occupied = $this->capacity->peak(InternshipPlacement::query()->where('partner_company_id', $company->id), today()->toDateString());

                if ((int) $data['internship_quota'] < max($allocated, $occupied)) {
                    throw ValidationException::withMessages(['internship_quota' => 'Kuota tidak boleh lebih kecil dari alokasi unit aktif atau penempatan yang sudah dijadwalkan.']);
                }
            }

            $company->fill($data)->save();

            return $company;
        });
    }
}
