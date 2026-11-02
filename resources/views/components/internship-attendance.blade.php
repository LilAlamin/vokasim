@props(['attendance', 'timezone' => 'Asia/Jakarta'])

@if ($attendance)
    <dl class="grid grid-cols-2 gap-3 text-sm">
        <div><dt class="text-zinc-600 dark:text-zinc-400">Datang</dt><dd class="mt-1 font-medium">{{ $attendance->checked_in_at->setTimezone($timezone)->format('d M, H:i') }}</dd><dd class="mt-1 text-zinc-600 dark:text-zinc-300">{{ number_format($attendance->check_in_distance) }} m · Akurasi ±{{ number_format($attendance->check_in_accuracy) }} m</dd></div>
        <div><dt class="text-zinc-600 dark:text-zinc-400">Pulang</dt><dd class="mt-1 font-medium">{{ $attendance->checked_out_at?->setTimezone($timezone)->format('d M, H:i') ?? 'Belum tercatat' }}</dd>@if ($attendance->checked_out_at)<dd class="mt-1 text-zinc-600 dark:text-zinc-300">{{ number_format($attendance->check_out_distance) }} m · Akurasi ±{{ number_format($attendance->check_out_accuracy) }} m</dd>@endif</div>
        <div class="col-span-2"><dt class="sr-only">Status geofencing</dt><dd class="text-emerald-700 dark:text-emerald-300">Dalam radius {{ $attendance->radius_meters }} m saat {{ $attendance->checked_out_at ? 'datang dan pulang' : 'datang' }}</dd><dd class="mt-1 text-zinc-600 dark:text-zinc-300">{{ $timezone }} · Durasi presensi {{ number_format($attendance->elapsedMinutes() / 60, 2) }} jam</dd></div>
    </dl>
@else
    <flux:text>Belum ada presensi untuk tanggal ini.</flux:text>
@endif
