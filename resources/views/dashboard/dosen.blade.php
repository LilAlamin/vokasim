<div class="flex flex-col gap-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-3"><flux:heading size="xl" level="1" class="break-words">Selamat datang, {{ auth()->user()->name }}</flux:heading><flux:badge>Dosen / Instruktur</flux:badge></div>
            <flux:text class="mt-2">{{ auth()->user()->study_program ?: 'Program studi belum diisi' }} · {{ auth()->user()->identifier_number ?: 'NIP / NIDN belum diisi' }}</flux:text>
        </div>
        <flux:button :href="route('practicums.index')" wire:navigate>Kelola kelas praktik</flux:button>
    </header>

    <dl class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900"><dt class="text-sm text-zinc-600 dark:text-zinc-300">Kelas Praktik Diampu</dt><dd class="mt-2 text-3xl font-semibold tabular-nums">{{ $practicumModuleCount }}</dd><dd class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">Modul yang Anda kelola, termasuk draf</dd></div>
        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900"><dt class="text-sm text-zinc-600 dark:text-zinc-300">Mahasiswa Praktikum</dt><dd class="mt-2 text-3xl font-semibold tabular-nums">{{ $practicumStudentCount }}</dd><dd class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">Peserta aktif di kelas yang Anda ampu</dd></div>
        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900"><dt class="text-sm text-zinc-600 dark:text-zinc-300">Laporan Perlu Penilaian</dt><dd class="mt-2 text-3xl font-semibold tabular-nums">{{ $practicumPendingCount }}</dd><dd class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">Pengiriman terbaru dari setiap tugas</dd></div>
        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900"><dt class="text-sm text-zinc-600 dark:text-zinc-300">Rata-rata Skor Praktik</dt><dd class="mt-2 text-3xl font-semibold tabular-nums">{{ $practicumAverageGrade !== null ? number_format($practicumAverageGrade, 1) : '—' }}</dd><dd class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">Pengiriman terbaru yang sudah dinilai · skala 100</dd></div>
    </dl>

    <div class="grid items-start gap-6 xl:grid-cols-3">
        <div class="flex min-w-0 flex-col gap-6 xl:col-span-2">
            <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <flux:heading size="lg" level="2">Antrean penilaian praktikum</flux:heading>
                <div class="mt-4 divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse ($practicumPendingSubmissions as $submission)
                        <article class="flex flex-wrap items-center justify-between gap-3 py-4 first:pt-0 last:pb-0">
                            <div class="min-w-0"><flux:heading level="3" class="break-words">{{ $submission->student->name }}</flux:heading><flux:text class="mt-1">{{ $submission->assignment->title }} · {{ $submission->assignment->module->title }}</flux:text><flux:text class="mt-1">Dikirim {{ $submission->submitted_at->setTimezone(config('app.timezone'))->format('d M Y, H:i T') }}{{ $submission->is_late ? ' · Terlambat' : '' }}</flux:text></div>
                            <flux:button size="sm" :href="route('practicums.assignments.show', [$submission->assignment->module, $submission->assignment])" wire:navigate>Nilai laporan</flux:button>
                        </article>
                    @empty
                        <flux:text>Belum ada laporan yang menunggu penilaian. Kiriman mahasiswa akan muncul di sini.</flux:text>
                    @endforelse
                </div>
            </section>

            <x-internship-dashboard-logbooks :entries="$internshipRecentLogbooks" heading="Antrean Verifikasi Logbook Magang" />

            <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex flex-wrap items-center justify-between gap-3"><flux:heading size="lg" level="2">Pantauan wawancara mahasiswa</flux:heading><flux:button size="sm" :href="route('interviews.index')" wire:navigate>Riwayat wawancara</flux:button></div>
                <flux:text class="mt-2">{{ $interviewSummary['completed_count'] }} sesi selesai dari mahasiswa bimbingan · rata-rata {{ $interviewSummary['average_score'] !== null ? number_format($interviewSummary['average_score'], 1).' / 100' : 'belum tersedia' }}.</flux:text>
                <div class="mt-4 divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse ($recentInterviews as $interview)
                        <article class="flex flex-wrap items-center justify-between gap-3 py-4 first:pt-0 last:pb-0">
                            <div class="min-w-0"><flux:heading level="3" class="break-words">{{ $interview->student->name }}</flux:heading><flux:text class="mt-1">{{ $interview->target_position }} · {{ $interview->status->label() }}</flux:text>@if ($interview->status === \App\Enums\InterviewStatus::Completed)<flux:text class="mt-1">Skor {{ $interview->overall_score !== null ? number_format($interview->overall_score, 1).' / 100' : 'belum tersedia' }} · STAR {{ $interview->star_score !== null ? number_format($interview->star_score, 1) : '—' }} · Teknis {{ $interview->technical_score !== null ? number_format($interview->technical_score, 1) : '—' }}</flux:text>@endif</div>
                            <flux:button size="sm" :href="route('interviews.session', $interview)" wire:navigate>Tinjau sesi</flux:button>
                        </article>
                    @empty
                        <flux:text>Mahasiswa bimbingan belum memiliki sesi latihan. Riwayat mereka akan tampil setelah latihan dimulai.</flux:text>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="flex min-w-0 flex-col gap-6">
            <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <flux:heading size="lg" level="2">Jadwal kelas & ujian</flux:heading>
                <div class="mt-4 divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse ($upcomingSessions as $session)
                        <article class="flex flex-col gap-2 py-4 first:pt-0 last:pb-0">
                            <div class="flex flex-wrap items-center gap-2"><flux:badge size="sm" :color="$session->is_exam ? 'amber' : 'zinc'">{{ $session->is_exam ? 'Ujian' : 'Praktik' }}</flux:badge><span class="text-sm text-zinc-600 dark:text-zinc-300">{{ $session->starts_at->setTimezone($session->module->timezone)->format('d M Y, H:i T') }}</span></div>
                            <flux:heading level="3" class="break-words">{{ $session->title }}</flux:heading>
                            <flux:text>{{ $session->module->title }} · {{ $session->room ?: 'Ruang belum ditentukan' }}</flux:text>
                            <flux:button size="sm" class="self-start" :href="route('practicums.show', $session->module)" wire:navigate>Kelola sesi</flux:button>
                        </article>
                    @empty
                        <flux:text>Belum ada sesi atau ujian mendatang. Atur jadwal melalui kelas praktik Anda.</flux:text>
                    @endforelse
                </div>
                <flux:text class="mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-800">{{ $practicumAttendanceCount }} catatan hadir / terlambat pada sesi yang sudah dimulai.</flux:text>
            </section>

            <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <flux:heading size="lg" level="2">Bimbingan magang</flux:heading>
                <dl class="mt-4 flex flex-col gap-3 text-sm"><div class="flex justify-between gap-4"><dt>Mahasiswa pada penempatan aktif</dt><dd class="font-semibold tabular-nums">{{ $internshipStudentCount }}</dd></div><div class="flex justify-between gap-4"><dt>Logbook menunggu verifikasi</dt><dd class="font-semibold tabular-nums">{{ $internshipPendingCount }}</dd></div><div class="flex justify-between gap-4"><dt>Jam terverifikasi seluruh periode</dt><dd class="font-semibold tabular-nums">{{ number_format($internshipVerifiedHours, 1) }}</dd></div></dl>
                <div class="mt-4 flex flex-wrap gap-2"><flux:button size="sm" :href="route('industry.index')" wire:navigate>Rekap magang</flux:button><flux:button size="sm" :href="route('internships.evaluations')" wire:navigate>Evaluasi industri</flux:button></div>
            </section>

            <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <flux:heading size="lg" level="2">Bukti kompetensi bimbingan</flux:heading>
                <flux:text class="mt-2">{{ $portfolioProjectCount }} proyek portofolio · {{ $verifiedCertificateCount }} sertifikat terverifikasi yang masih berlaku.</flux:text>
                <flux:text class="mt-2">{{ $pendingCertificateCount }} sertifikat menunggu verifikasi.</flux:text>
                <div class="mt-4 flex flex-wrap gap-2"><flux:button size="sm" :href="route('certificates.index')" wire:navigate>Tinjau sertifikat</flux:button><flux:button size="sm" :href="route('portfolios.index')" wire:navigate>Portofolio mahasiswa</flux:button></div>
            </section>
        </div>
    </div>
</div>
