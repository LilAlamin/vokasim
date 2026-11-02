<?php

namespace App\Actions;

use App\Models\InternshipPlacement;
use App\Models\PartnerCompany;
use App\Models\PartnerUnit;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class CheckPartnerCapacity
{
    public function assertAvailable(PartnerCompany $company, ?PartnerUnit $unit, string $startsOn, string $endsOn, ?int $exceptPlacementId = null): void
    {
        $placements = InternshipPlacement::query()
            ->when($exceptPlacementId, fn (Builder $query) => $query->whereKeyNot($exceptPlacementId));

        if ($this->peak((clone $placements)->where('partner_company_id', $company->id), $startsOn, $endsOn) >= $company->internship_quota) {
            throw ValidationException::withMessages(['partner_company_id' => 'Kuota perusahaan sudah penuh pada sebagian periode ini. Pilih periode atau mitra lain.']);
        }

        if ($unit && $this->peak((clone $placements)->where('partner_unit_id', $unit->id), $startsOn, $endsOn) >= $unit->internship_quota) {
            throw ValidationException::withMessages(['partner_unit_id' => 'Kuota unit kerja sudah penuh pada sebagian periode ini.']);
        }
    }

    /**
     * Dates are inclusive. Events share a date so successive placements do not consume two seats.
     *
     * @param  Builder<InternshipPlacement>  $placements
     */
    public function peak(Builder $placements, string $startsOn, ?string $endsOn = null): int
    {
        $events = [];
        $placements->whereDate('ends_on', '>=', $startsOn)
            ->when($endsOn !== null, fn (Builder $query) => $query->whereDate('starts_on', '<=', $endsOn));

        foreach ($placements->get(['starts_on', 'ends_on']) as $placement) {
            $start = max($startsOn, $placement->starts_on->toDateString());
            $end = $endsOn === null ? $placement->ends_on->toDateString() : min($endsOn, $placement->ends_on->toDateString());
            $afterEnd = CarbonImmutable::parse($end)->addDay()->toDateString();
            $events[$start] = ($events[$start] ?? 0) + 1;
            $events[$afterEnd] = ($events[$afterEnd] ?? 0) - 1;
        }

        ksort($events);
        $current = 0;
        $peak = 0;

        foreach ($events as $change) {
            $current += $change;
            $peak = max($peak, $current);
        }

        return $peak;
    }
}
