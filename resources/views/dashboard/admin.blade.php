<div class="flex flex-col gap-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div><flux:heading size="xl" level="1">Executive Analytics Dashboard</flux:heading><flux:text class="mt-2">Ringkasan akademik, kemitraan industri, dan hasil tracer study dari data yang tercatat.</flux:text></div>
        <div class="flex flex-wrap gap-2"><flux:button :href="route('partners.index')" wire:navigate>Kelola mitra</flux:button><flux:button :href="route('tracer.index')" wire:navigate>Analisis tracer study</flux:button></div>
    </header>

    <dl class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900"><dt class="text-sm text-zinc-600 dark:text-zinc-300">Keterserapan Lulusan di DUDI</dt><dd class="mt-2 text-3xl font-semibold tabular-nums">{{ $tracerSummary['absorption_percentage'] !== null ? number_format($tracerSummary['absorption_percentage'], 1).'%' : '—' }}</dd><dd class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ $tracerSummary['working_count'] }} bekerja / berwirausaha dari {{ $tracerSummary['total_responses'] }} responden</dd></div>
        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900"><dt class="text-sm text-zinc-600 dark:text-zinc-300">Kemitraan DUDI Aktif</dt><dd class="mt-2 text-3xl font-semibold tabular-nums">{{ $partnerSummary['active_companies'] }}</dd><dd class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ $partnerSummary['total_quota'] }} total kuota magang terdaftar</dd></div>
        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900"><dt class="text-sm text-zinc-600 dark:text-zinc-300">Mahasiswa Aktif</dt><dd class="mt-2 text-3xl font-semibold tabular-nums">{{ $adminSummary['student_count'] }}</dd><dd class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ $internshipStudentCount }} mahasiswa pada penempatan magang aktif</dd></div>
        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900"><dt class="text-sm text-zinc-600 dark:text-zinc-300">Rata-rata Skor Wawancara</dt><dd class="mt-2 text-3xl font-semibold tabular-nums">{{ $adminSummary['interview_average_score'] !== null ? number_format($adminSummary['interview_average_score'], 1) : '—' }}</dd><dd class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ $adminSummary['interview_scored_count'] }} sesi selesai dengan skor · skala 100</dd></div>
    </dl>

    <div class="grid items-start gap-6 xl:grid-cols-3">
        <div class="flex min-w-0 flex-col gap-6 xl:col-span-2">
            <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <flux:heading size="lg" level="2">Skor wawancara menurut program studi</flux:heading>
                <flux:text class="mt-2">Rata-rata sesi yang selesai. Menampilkan hingga delapan program studi dengan sesi terbanyak.</flux:text>
                <div class="mt-4 overflow-x-auto" tabindex="0" aria-label="Tabel agregat skor wawancara">
                    <table class="w-full min-w-144 text-left text-sm">
                        <caption class="sr-only">Jumlah sesi selesai dan rata-rata skor wawancara per program studi</caption>
                        <thead class="border-b border-zinc-200 text-zinc-600 dark:border-zinc-800 dark:text-zinc-300"><tr><th scope="col" class="py-3 pr-4 font-medium">Program studi</th><th scope="col" class="px-3 py-3 text-right font-medium">Sesi dinilai</th><th scope="col" class="px-3 py-3 text-right font-medium">Total</th><th scope="col" class="px-3 py-3 text-right font-medium">STAR</th><th scope="col" class="px-3 py-3 text-right font-medium">Teknis</th><th scope="col" class="py-3 pl-3 text-right font-medium">Komunikasi</th></tr></thead>
                        <tbody>
                            @forelse ($interviewProgramScores as $program)
                                <tr class="border-b border-zinc-200 align-top dark:border-zinc-800"><th scope="row" class="py-4 pr-4 font-medium">{{ $program['study_program'] }}</th><td class="px-3 py-4 text-right tabular-nums">{{ $program['scored_count'] }} / {{ $program['session_count'] }}</td><td class="px-3 py-4 text-right tabular-nums">{{ $program['overall_score'] !== null ? number_format($program['overall_score'], 1) : '—' }}</td><td class="px-3 py-4 text-right tabular-nums">{{ $program['star_score'] !== null ? number_format($program['star_score'], 1) : '—' }}</td><td class="px-3 py-4 text-right tabular-nums">{{ $program['technical_score'] !== null ? number_format($program['technical_score'], 1) : '—' }}</td><td class="py-4 pl-3 text-right tabular-nums">{{ $program['communication_score'] !== null ? number_format($program['communication_score'], 1) : '—' }}</td></tr>
                            @empty
                                <tr><td colspan="6" class="py-6 text-zinc-600 dark:text-zinc-300">Belum ada sesi wawancara yang selesai. Agregat akan tersedia setelah mahasiswa menerima evaluasi.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex flex-wrap items-center justify-between gap-3"><flux:heading size="lg" level="2">Hasil tracer study</flux:heading><flux:button size="sm" :href="route('tracer.index')" wire:navigate>Filter & rincian</flux:button></div>
                <flux:text class="mt-2">{{ $tracerSummary['total_responses'] }} responden dari seluruh angkatan dan program studi. Persentase menggambarkan responden survei.</flux:text>
                <dl class="mt-5 grid gap-5 sm:grid-cols-3">
                    <div><dt class="text-sm text-zinc-600 dark:text-zinc-300">Waktu tunggu kerja pertama</dt><dd class="mt-1 text-xl font-semibold tabular-nums">{{ $tracerSummary['average_wait_months'] !== null ? number_format($tracerSummary['average_wait_months'], 1).' bulan' : '—' }}</dd><dd class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ $tracerSummary['wait_sample'] }} responden</dd></div>
                    <div><dt class="text-sm text-zinc-600 dark:text-zinc-300">Gaji pertama rata-rata</dt><dd class="mt-1 text-xl font-semibold tabular-nums">{{ $tracerSummary['average_first_salary'] !== null ? 'Rp '.number_format($tracerSummary['average_first_salary'], 0, ',', '.') : '—' }}</dd><dd class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ $tracerSummary['salary_sample'] }} responden mengisi gaji</dd></div>
                    <div><dt class="text-sm text-zinc-600 dark:text-zinc-300">Pekerjaan sesuai bidang</dt><dd class="mt-1 text-xl font-semibold tabular-nums">{{ $tracerSummary['alignment_percentage'] !== null ? number_format($tracerSummary['alignment_percentage'], 1).'%' : '—' }}</dd><dd class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ $tracerSummary['alignment_sample'] }} responden bekerja dengan data kesesuaian</dd></div>
                </dl>
                <dl class="mt-5 grid gap-x-6 gap-y-3 border-t border-zinc-200 pt-4 text-sm sm:grid-cols-2 dark:border-zinc-800">
                    @foreach (\App\Models\TracerResponse::employmentStatuses() as $status => $label)
                        <div class="flex justify-between gap-4"><dt>{{ $label }}</dt><dd class="font-semibold tabular-nums">{{ $tracerSummary['status_counts'][$status] }}</dd></div>
                    @endforeach
                </dl>
            </section>

            <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <flux:heading size="lg" level="2">Masukan kompetensi dari alumni</flux:heading>
                <flux:text class="mt-2">Kebutuhan keterampilan yang paling sering disebut dalam survei.</flux:text>
                <dl class="mt-4 flex flex-col gap-3 text-sm">
                    @forelse (array_slice($tracerSummary['skill_gaps'], 0, 5, true) as $skill => $count)
                        <div class="flex justify-between gap-4"><dt class="break-words">{{ $skill }}</dt><dd class="shrink-0 font-semibold tabular-nums">{{ $count }} responden</dd></div>
                    @empty
                        <div><flux:text>Belum ada masukan keterampilan dari alumni.</flux:text></div>
                    @endforelse
                </dl>
            </section>
        </div>

        <div class="flex min-w-0 flex-col gap-6">
            <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex flex-wrap items-center justify-between gap-3"><flux:heading size="lg" level="2">Perjanjian segera berakhir</flux:heading><flux:badge :color="$partnerSummary['expiring_agreements'] > 0 ? 'amber' : 'zinc'">{{ $partnerSummary['expiring_agreements'] }}</flux:badge></div>
                <flux:text class="mt-2">MoU / MoA aktif yang berakhir dalam 30 hari, termasuk hari ini.</flux:text>
                <div class="mt-4 divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse ($expiringAgreements as $agreement)
                        <article class="py-4 first:pt-0 last:pb-0"><flux:heading level="3" class="break-words">{{ $agreement->company->name }}</flux:heading><flux:text class="mt-1">{{ strtoupper($agreement->kind) }} · {{ $agreement->reference_number }}</flux:text><flux:text class="mt-1">Berakhir {{ $agreement->expires_on->format('d M Y') }}</flux:text></article>
                    @empty
                        <flux:text>Tidak ada perjanjian aktif yang mendekati akhir masa berlaku.</flux:text>
                    @endforelse
                </div>
                <flux:button size="sm" class="mt-4" :href="route('partners.index')" wire:navigate>Tinjau perjanjian</flux:button>
            </section>

            <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <flux:heading size="lg" level="2">Kapasitas akademik</flux:heading>
                <dl class="mt-4 flex flex-col gap-3 text-sm"><div class="flex justify-between gap-4"><dt>Dosen aktif</dt><dd class="font-semibold tabular-nums">{{ $adminSummary['lecturer_count'] }}</dd></div><div class="flex justify-between gap-4"><dt>Mahasiswa per dosen aktif</dt><dd class="font-semibold tabular-nums">{{ $adminSummary['student_lecturer_ratio'] !== null ? number_format($adminSummary['student_lecturer_ratio'], 1).' : 1' : '—' }}</dd></div><div class="flex justify-between gap-4"><dt>Modul praktikum</dt><dd class="font-semibold tabular-nums">{{ $adminSummary['practicum_module_count'] }}</dd></div><div class="flex justify-between gap-4"><dt>Pendaftaran kelas aktif</dt><dd class="font-semibold tabular-nums">{{ $adminSummary['practicum_enrollment_count'] }}</dd></div><div class="flex justify-between gap-4"><dt>Mentor industri aktif</dt><dd class="font-semibold tabular-nums">{{ $adminSummary['mentor_count'] }}</dd></div></dl>
                <flux:button size="sm" class="mt-4" :href="route('practicums.index')" wire:navigate>Kelola praktikum</flux:button>
            </section>

            <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <flux:heading size="lg" level="2">Bukti kompetensi & magang</flux:heading>
                <flux:text class="mt-2">{{ $portfolioProjectCount }} proyek portofolio · {{ $verifiedCertificateCount }} sertifikat terverifikasi yang masih berlaku.</flux:text>
                <flux:text class="mt-2">{{ $pendingCertificateCount }} sertifikat menunggu verifikasi. {{ number_format($internshipVerifiedHours, 1) }} jam magang sudah diverifikasi dosen.</flux:text>
                <div class="mt-4 flex flex-wrap gap-2"><flux:button size="sm" :href="route('certificates.index')" wire:navigate>Sertifikat</flux:button><flux:button size="sm" :href="route('portfolios.index')" wire:navigate>Portofolio</flux:button><flux:button size="sm" :href="route('industry.index')" wire:navigate>Rekap magang</flux:button></div>
            </section>
        </div>
    </div>
</div>
