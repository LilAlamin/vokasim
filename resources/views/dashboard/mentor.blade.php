<div class="flex flex-col gap-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0"><div class="flex flex-wrap items-center gap-3"><flux:heading size="xl" level="1" class="break-words">Selamat datang, {{ auth()->user()->name }}</flux:heading><flux:badge>Mentor Industri DUDI</flux:badge></div><flux:text class="mt-2">Tinjau aktivitas, izin, dan capaian mahasiswa pada penempatan yang Anda bimbing.</flux:text></div>
        <flux:button :href="route('industry.index')" wire:navigate>Kelola proyek magang</flux:button>
    </header>

    <dl class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900"><dt class="text-sm text-zinc-600 dark:text-zinc-300">Mahasiswa Magang Aktif</dt><dd class="mt-2 text-3xl font-semibold tabular-nums">{{ $internshipStudentCount }}</dd><dd class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">Penempatan dalam lingkup bimbingan Anda</dd></div>
        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900"><dt class="text-sm text-zinc-600 dark:text-zinc-300">Presensi Geofencing GPS Aktif</dt><dd class="mt-2 text-3xl font-semibold tabular-nums">{{ $internshipAttendanceToday }}</dd><dd class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">Presensi datang hari ini menurut zona waktu lokasi</dd></div>
        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900"><dt class="text-sm text-zinc-600 dark:text-zinc-300">Logbook Perlu Validasi</dt><dd class="mt-2 text-3xl font-semibold tabular-nums">{{ $internshipPendingCount }}</dd><dd class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">Menunggu keputusan mentor</dd></div>
        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900"><dt class="text-sm text-zinc-600 dark:text-zinc-300">Izin & Sakit Menunggu</dt><dd class="mt-2 text-3xl font-semibold tabular-nums">{{ $mentorPendingLeaveCount }}</dd><dd class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">Pengajuan yang belum ditinjau</dd></div>
    </dl>

    <div class="grid items-start gap-6 xl:grid-cols-3">
        <div class="flex min-w-0 flex-col gap-6 xl:col-span-2">
            <x-internship-dashboard-logbooks :entries="$internshipRecentLogbooks" heading="Validasi Aktivitas" />

            <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex flex-wrap items-center justify-between gap-3"><flux:heading size="lg" level="2">Mahasiswa dalam penempatan aktif</flux:heading><flux:button size="sm" :href="route('industry.index')" wire:navigate>Lihat rekap</flux:button></div>
                <div class="mt-4 divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse ($mentorInterns as $placement)
                        <article class="flex flex-wrap items-start justify-between gap-3 py-4 first:pt-0 last:pb-0">
                            <div class="min-w-0"><flux:heading level="3" class="break-words">{{ $placement->student->name }}</flux:heading><flux:text class="mt-1">{{ $placement->company_name }}</flux:text><flux:text class="mt-1">Sampai {{ $placement->ends_on->format('d M Y') }}</flux:text></div>
                            <div class="text-sm"><p class="font-semibold tabular-nums">{{ number_format((float) $placement->getAttribute('verified_minutes') / 60, 1) }} / {{ $placement->target_hours }} jam</p><p class="mt-1 text-zinc-600 dark:text-zinc-300">Diverifikasi dosen</p></div>
                        </article>
                    @empty
                        <flux:text>Belum ada penempatan aktif. Administrator dapat menghubungkan Anda dengan mahasiswa dan lokasi magang.</flux:text>
                    @endforelse
                </div>
            </section>

            <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex flex-wrap items-center justify-between gap-3"><flux:heading size="lg" level="2">Proyek yang sedang dikerjakan</flux:heading><flux:button size="sm" :href="route('industry.index')" wire:navigate>Perbarui capaian</flux:button></div>
                <div class="mt-4 divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse ($mentorProjects as $project)
                        <article class="flex flex-col gap-2 py-4 first:pt-0 last:pb-0">
                            <div class="flex flex-wrap items-center justify-between gap-3"><flux:heading level="3" class="break-words">{{ $project->title }}</flux:heading><flux:badge>{{ $project->completionPercentage() }}% target</flux:badge></div>
                            <flux:text>{{ $project->placement->student->name }} · tenggat {{ $project->due_on->format('d M Y') }}</flux:text>
                            <flux:text>{{ number_format($project->completed_minutes / 60, 1) }} dari {{ number_format($project->target_minutes / 60, 1) }} jam proyek ditinjau. Jam magang mengikuti validasi logbook.</flux:text>
                        </article>
                    @empty
                        <flux:text>Belum ada proyek dengan target yang belum selesai. Tetapkan proyek melalui rekap magang.</flux:text>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="flex min-w-0 flex-col gap-6">
            <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex flex-wrap items-center justify-between gap-3"><flux:heading size="lg" level="2">Pengajuan izin & sakit</flux:heading><flux:button size="sm" :href="route('internships.leaves')" wire:navigate>Tinjau pengajuan</flux:button></div>
                <div class="mt-4 divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse ($mentorPendingLeaves as $leave)
                        <article class="py-4 first:pt-0 last:pb-0"><flux:heading level="3" class="break-words">{{ $leave->placement->student->name }}</flux:heading><flux:text class="mt-1">{{ $leave->kind === 'sick' ? 'Sakit' : 'Izin' }} · {{ $leave->starts_on->format('d M Y') }} – {{ $leave->ends_on->format('d M Y') }}</flux:text></article>
                    @empty
                        <flux:text>Semua pengajuan sudah ditinjau atau belum ada pengajuan dari mahasiswa.</flux:text>
                    @endforelse
                </div>
            </section>

            <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <flux:heading size="lg" level="2">Evaluasi kinerja</flux:heading>
                <flux:text class="mt-2">Penempatan yang belum memiliki evaluasi untuk periode berikut.</flux:text>
                <dl class="mt-4 flex flex-col gap-3 text-sm"><div class="flex justify-between gap-4"><dt>Bulanan pada penempatan aktif</dt><dd class="font-semibold tabular-nums">{{ $mentorMonthlyEvaluationCount }}</dd></div><div class="flex justify-between gap-4"><dt>Akhir saat tanggal selesai tercapai</dt><dd class="font-semibold tabular-nums">{{ $mentorFinalEvaluationCount }}</dd></div></dl>
                <flux:button size="sm" class="mt-4" :href="route('internships.evaluations')" wire:navigate>Isi evaluasi</flux:button>
            </section>
        </div>
    </div>
</div>
