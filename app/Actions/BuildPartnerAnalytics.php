<?php

namespace App\Actions;

use App\Models\PartnerAgreement;
use App\Models\PartnerCompany;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class BuildPartnerAnalytics
{
    /** @return array{active_companies: int, total_quota: int, current_placements: int, expiring_agreements: int, expired_agreements: int, unmapped_companies: int, regions: array<string, int>, locations: list<array{id: int, name: string, city: string, province: string, latitude: float, longitude: float, placements: int}>} */
    public function handle(User $user): array
    {
        Gate::forUser($user)->authorize('viewAny', PartnerCompany::class);
        $companies = PartnerCompany::query()->where('is_active', true)
            ->withCount(['placements as current_placements' => fn ($query) => $query->whereDate('starts_on', '<=', today())->whereDate('ends_on', '>=', today())])
            ->orderBy('name')->get();
        $regions = [];
        $locations = [];
        $unmapped = 0;
        $currentPlacements = 0;

        foreach ($companies as $company) {
            $regions[$company->province] = ($regions[$company->province] ?? 0) + 1;
            $occupied = (int) $company->getAttribute('current_placements');
            $currentPlacements += $occupied;

            if ($company->latitude === null || $company->longitude === null) {
                $unmapped++;

                continue;
            }

            $locations[] = [
                'id' => $company->id,
                'name' => $company->name,
                'city' => $company->city,
                'province' => $company->province,
                'latitude' => $company->latitude,
                'longitude' => $company->longitude,
                'placements' => $occupied,
            ];
        }

        arsort($regions);

        return [
            'active_companies' => $companies->count(),
            'total_quota' => (int) $companies->sum('internship_quota'),
            'current_placements' => $currentPlacements,
            'expiring_agreements' => PartnerAgreement::query()->expiringSoon()->count(),
            'expired_agreements' => PartnerAgreement::query()->where('is_active', true)->whereDate('expires_on', '<', today())->count(),
            'unmapped_companies' => $unmapped,
            'regions' => $regions,
            'locations' => $locations,
        ];
    }
}
