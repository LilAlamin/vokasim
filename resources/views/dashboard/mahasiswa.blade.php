<div class="flex flex-col gap-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <flux:heading size="xl" level="1" class="break-words">Selamat datang, {{ auth()->user()->name }}</flux:heading>
            <flux:text class="mt-2">{{ auth()->user()->study_program ?: 'Program studi belum diisi' }} · {{ auth()->user()->identifier_number ?: 'NIM belum diisi' }}</flux:text>
            <flux:text class="mt-1">Semester {{ auth()->user()->semester ?? '—' }} · IPK {{ auth()->user()->gpa !== null ? number_format(auth()->user()->gpa, 2) : '—' }}</flux:text>
        </div>
        <flux:button :href="route('practicums.index')" wire:navigate>Buka kelas praktik</flux:button>
    </header>

    <dl class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
            <dt class="text-sm text-zinc-600 dark:text-zinc-300">Kompetensi Praktik</dt>
            <dd class="mt-2 text-3xl font-semibold tabular-nums">{{ $practicumSummary['completion_percentage'] !== null ? $practicumSummary['completion_percentage'].'%' : '—' }}</dd>
            <dd class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ $practicumSummary['graded_count'] }} dari {{ $practicumSummary['assignment_count'] }} tugas selesai dinilai</dd>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
            <dt class="text-sm text-zinc-600 dark:text-zinc-300">Jam Magang DUDI</dt>
            <dd class="mt-2 text-3xl font-semibold tabular-nums">{{ number_format($internshipVerifiedHours, 1) }}</dd>
            <dd class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ $internshipActivePlacement ? 'Terverifikasi dari target '.$internshipTargetHours.' jam' : 'Belum ada penempatan aktif' }}</dd>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
            <dt class="text-sm text-zinc-600 dark:text-zinc-300">Sertifikat Terverifikasi</dt>
            <dd class="mt-2 text-3xl font-semibold tabular-nums">{{ $verifiedCertificateCount }}</dd>
            <dd class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">Masih berlaku · {{ $pendingCertificateCount }} menunggu verifikasi</dd>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
            <dt class="text-sm text-zinc-600 dark:text-zinc-300">Skor Wawancara Terakhir</dt>
            <dd class="mt-2 text-3xl font-semibold tabular-nums">{{ $interviewSummary['latest_score'] !== null ? number_format($interviewSummary['latest_score'], 1) : '—' }}</dd>
            <dd class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ $interviewSummary['completed_count'] }} sesi selesai · skala 100</dd>
        </div>
    </dl>

    <div class="grid items-start gap-6 xl:grid-cols-3">
        <div class="flex min-w-0 flex-col gap-6 xl:col-span-2">
            <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <flux:heading size="lg" level="2">Progres kelas praktik</flux:heading>
                    <flux:button size="sm" :href="route('practicums.index')" wire:navigate>Semua kelas ({{ $practicumSummary['module_count'] }})</flux:button>
                </div>
                <flux:text class="mt-2">Kehadiran: {{ $practicumSummary['attended_count'] }} dari {{ $practicumSummary['session_count'] }} sesi yang sudah dimulai{{ $practicumSummary['attendance_percentage'] !== null ? ' · '.$practicumSummary['attendance_percentage'].'%' : '' }}.</flux:text>
                <div class="mt-4 divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse ($practicumModules as $item)
                        <article class="flex flex-col gap-3 py-4 first:pt-0 last:pb-0">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div class="min-w-0"><flux:heading level="3" class="break-words">{{ $item['module']->title }}</flux:heading><flux:text>{{ $item['module']->code }}</flux:text></div>
                                <flux:button size="sm" :href="route('practicums.show', $item['module'])" wire:navigate>Buka kelas</flux:button>
                            </div>
                            <x-practicum-progress :progress="$item['progress']" />
                        </article>
                    @empty
                        <flux:text>Belum ada kelas aktif yang diterbitkan. Dosen dapat mendaftarkan Anda ke modul praktikum.</flux:text>
                    @endforelse
                </div>
            </section>

            <x-internship-dashboard-logbooks :entries="$internshipRecentLogbooks" heading="Aktivitas magang terbaru" />

            <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <flux:heading size="lg" level="2">Simulasi Wawancara HRD AI</flux:heading>
                    <flux:button :href="route('interviews.index')" wire:navigate>Mulai latihan</flux:button>
                </div>
                <flux:text class="mt-2">Latih jawaban sesuai posisi tujuan dan tinjau evaluasi dari sesi Anda.</flux:text>
                <div class="mt-4 divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse ($recentInterviews as $interview)
                        <article class="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                            <div class="min-w-0"><flux:heading level="3" class="break-words">{{ $interview->target_position }}</flux:heading><flux:text class="mt-1">{{ $interview->created_at->setTimezone(config('app.timezone'))->format('d M Y') }} · {{ $interview->status->label() }}@if ($interview->status === \App\Enums\InterviewStatus::Completed && $interview->overall_score !== null) · {{ number_format($interview->overall_score, 1) }} / 100 @endif</flux:text></div>
                            <flux:button size="sm" :href="route('interviews.session', $interview)" wire:navigate>Buka sesi</flux:button>
                        </article>
                    @empty
                        <flux:text>Belum ada sesi wawancara. Mulai latihan pertama untuk mendapatkan evaluasi.</flux:text>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="flex min-w-0 flex-col gap-6">
            <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <flux:heading size="lg" level="2">Jadwal praktik & ujian</flux:heading>
                <div class="mt-4 divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse ($upcomingSessions as $session)
                        <article class="flex flex-col gap-2 py-4 first:pt-0 last:pb-0">
                            <div class="flex flex-wrap items-center gap-2"><flux:badge size="sm" :color="$session->is_exam ? 'amber' : 'zinc'">{{ $session->is_exam ? 'Ujian' : 'Praktik' }}</flux:badge><span class="text-sm text-zinc-600 dark:text-zinc-300">{{ $session->starts_at->setTimezone($session->module->timezone)->format('d M Y, H:i T') }}</span></div>
                            <flux:heading level="3" class="break-words">{{ $session->title }}</flux:heading>
                            <flux:text>{{ $session->module->title }} · {{ $session->room ?: 'Ruang belum ditentukan' }}</flux:text>
                            <flux:button size="sm" class="self-start" :href="route('practicums.show', $session->module)" wire:navigate>Detail kelas</flux:button>
                        </article>
                    @empty
                        <flux:text>Belum ada sesi atau ujian mendatang. Jadwal akan tampil setelah diterbitkan dosen.</flux:text>
                    @endforelse
                </div>
            </section>

            @if ($internshipActivePlacement)
                <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                    <flux:heading size="lg" level="2">Penempatan magang</flux:heading>
                    <flux:heading level="3" class="mt-4 break-words">{{ $internshipActivePlacement->company_name }}</flux:heading>
                    <flux:text class="mt-2">{{ $internshipActivePlacement->starts_on->format('d M Y') }} – {{ $internshipActivePlacement->ends_on->format('d M Y') }}</flux:text>
                    <flux:text class="mt-2">Mentor: {{ $internshipActivePlacement->mentor->name }}<br>Pembimbing: {{ $internshipActivePlacement->lecturer->name }}</flux:text>
                    <div class="mt-4 flex flex-wrap gap-2"><flux:button size="sm" :href="route('internships.leaves')" wire:navigate>Izin & sakit</flux:button><flux:button size="sm" :href="route('internships.evaluations')" wire:navigate>Evaluasi kinerja</flux:button><flux:button size="sm" :href="route('industry.index')" wire:navigate>Proyek & rekap</flux:button></div>
                </section>
            @endif

            <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <flux:heading size="lg" level="2">Portofolio & arah karier</flux:heading>
                <flux:text class="mt-2">{{ $portfolioProjectCount }} proyek tersimpan. Kecocokan karier menggunakan bukti kompetensi yang sudah dinilai atau diverifikasi.</flux:text>
                <div class="mt-4 flex flex-col gap-4">
                    @forelse ($careerMatches as $career)
                        <div><flux:heading level="3">{{ $career['title'] }}</flux:heading><flux:text class="mt-1">{{ $career['match_percentage'] }}% kompetensi cocok · {{ implode(', ', $career['matched_skills']) }}</flux:text></div>
                    @empty
                        <flux:text>Belum ada bukti kompetensi yang cocok. Lengkapi tugas praktik atau ajukan sertifikat untuk diverifikasi.</flux:text>
                    @endforelse
                </div>
                <div class="mt-4 flex flex-wrap gap-2"><flux:button size="sm" :href="route('portfolios.index')" wire:navigate>Portofolio</flux:button><flux:button size="sm" :href="route('certificates.index')" wire:navigate>Sertifikat</flux:button><flux:button size="sm" :href="route('careers.index')" wire:navigate>Jelajahi karier</flux:button></div>
                <div class="mt-5 border-t border-zinc-200 pt-4 dark:border-zinc-800"><flux:heading level="3">Tracer study alumni</flux:heading><flux:text class="mt-1">Setelah lulus, bagikan perjalanan kerja Anda untuk evaluasi program studi.</flux:text><flux:button size="sm" class="mt-3" :href="route('tracer.survey')" wire:navigate>{{ $hasTracerResponse ? 'Perbarui survei alumni' : 'Isi survei alumni' }}</flux:button></div>
            </section>
        </div>
    </div>
</div>
