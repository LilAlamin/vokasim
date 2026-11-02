<?php

namespace App\Actions;

use App\Models\InternshipAttendance;
use App\Models\InternshipPlacement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RecordInternshipAttendance
{
    /** @param array<string, mixed> $coordinates */
    public function handle(User $user, int $placementId, string $type, array $coordinates): InternshipAttendance
    {
        return DB::transaction(function () use ($user, $placementId, $type, $coordinates): InternshipAttendance {
            $placement = InternshipPlacement::query()->forUser($user)->lockForUpdate()->findOrFail($placementId);
            Gate::forUser($user)->authorize('attend', $placement);

            $data = Validator::make([...$coordinates, 'type' => $type], [
                'type' => ['required', Rule::in(['in', 'out'])],
                'latitude' => ['required', 'numeric', 'between:-90,90'],
                'longitude' => ['required', 'numeric', 'between:-180,180'],
                'accuracy' => ['required', 'numeric', 'gt:0', 'max:'.min(100, $placement->radius_meters)],
            ], [
                'latitude.required' => 'Izinkan akses lokasi lalu coba lagi.',
                'longitude.required' => 'Izinkan akses lokasi lalu coba lagi.',
                'accuracy.max' => 'GPS belum cukup akurat. Pindah ke area terbuka, lalu coba lagi.',
                'accuracy.gt' => 'Akurasi GPS tidak valid. Ambil ulang lokasi.',
            ])->validate();

            $distance = $this->distanceMeters($placement->latitude, $placement->longitude, (float) $data['latitude'], (float) $data['longitude']);

            if ($distance > $placement->radius_meters) {
                throw ValidationException::withMessages(['location' => 'Anda berada '.(int) ceil($distance).' m dari titik magang, di luar radius '.$placement->radius_meters.' m. Datang ke lokasi lalu coba lagi.']);
            }

            $now = CarbonImmutable::now('UTC');
            $date = $now->setTimezone($placement->timezone)->toDateString();
            $openAttendance = $placement->attendances()->whereNull('checked_out_at')->where('checked_in_at', '>=', $now->subHours(24))->latest('checked_in_at')->lockForUpdate()->first();

            if ($type === 'in') {
                if (! $placement->isActive()) {
                    throw ValidationException::withMessages(['attendance' => 'Presensi hanya tersedia selama periode magang aktif.']);
                }

                if ($placement->leaveRequests()->where('status', 'approved')->whereDate('starts_on', '<=', $date)->whereDate('ends_on', '>=', $date)->exists()) {
                    throw ValidationException::withMessages(['attendance' => 'Izin atau sakit sudah disetujui untuk hari ini. Hubungi mentor sebelum mencatat kehadiran.']);
                }

                if ($openAttendance) {
                    throw ValidationException::withMessages(['attendance' => 'Selesaikan presensi pulang yang masih terbuka sebelum presensi datang lagi.']);
                }

                if ($placement->attendances()->whereDate('work_date', $date)->exists()) {
                    throw ValidationException::withMessages(['attendance' => 'Presensi datang untuk hari ini sudah tercatat.']);
                }

                return $placement->attendances()->create([
                    'work_date' => $date, 'checked_in_at' => $now,
                    'check_in_latitude' => $data['latitude'], 'check_in_longitude' => $data['longitude'],
                    'check_in_accuracy' => $data['accuracy'], 'check_in_distance' => $distance,
                    'radius_meters' => $placement->radius_meters,
                ]);
            }

            if (! $openAttendance) {
                throw ValidationException::withMessages(['attendance' => 'Tidak ada presensi datang yang menunggu presensi pulang.']);
            }

            if ($openAttendance->checked_in_at->diffInMinutes($now) < 1 || $openAttendance->checked_in_at->diffInHours($now) > 24) {
                throw ValidationException::withMessages(['attendance' => 'Presensi pulang harus antara 1 menit dan 24 jam setelah datang. Hubungi admin jika presensi terlewat.']);
            }

            $openAttendance->update([
                'checked_out_at' => $now,
                'check_out_latitude' => $data['latitude'], 'check_out_longitude' => $data['longitude'],
                'check_out_accuracy' => $data['accuracy'], 'check_out_distance' => $distance,
            ]);

            return $openAttendance;
        });
    }

    private function distanceMeters(float $originLatitude, float $originLongitude, float $latitude, float $longitude): float
    {
        $latitudeDelta = deg2rad($latitude - $originLatitude);
        $longitudeDelta = deg2rad($longitude - $originLongitude);
        $haversine = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($originLatitude)) * cos(deg2rad($latitude)) * sin($longitudeDelta / 2) ** 2;

        return 6371000 * 2 * asin(sqrt(min(1, max(0, $haversine))));
    }
}
