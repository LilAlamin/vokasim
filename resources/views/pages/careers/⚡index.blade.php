<?php

use App\Services\CareerRecommendations;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Rekomendasi Jalur Karier')] class extends Component
{
    public function mount(): void
    {
        abort_unless(Auth::user()->isMahasiswa(), 403);
    }

    #[Computed]
    public function profile(): array
    {
        return app(CareerRecommendations::class)->forStudent(Auth::user());
    }
}; ?>

<section class="mx-auto flex w-full max-w-6xl flex-col gap-7">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div class="max-w-2xl">
            <flux:heading size="xl" level="1">Rekomendasi Jalur Karier</flux:heading>
            <flux:text class="mt-2">Temukan arah latihan berikutnya dari kompetensi yang sudah didukung bukti dan keterampilan yang perlu dikembangkan.</flux:text>
        </div>
        <div class="flex flex-wrap gap-2">
            <flux:button :href="route('portfolios.index')" wire:navigate>Portofolio saya</flux:button>
            <flux:button :href="route('certificates.index')" wire:navigate variant="primary">Lengkapi sertifikat</flux:button>
        </div>
    </header>

    <section class="flex flex-col gap-3 rounded-xl bg-zinc-100 p-5 dark:bg-zinc-900" aria-label="Cara membaca rekomendasi">
        <flux:heading level="2">Cara rekomendasi dihitung</flux:heading>
        <p class="max-w-prose text-sm leading-relaxed text-zinc-700 dark:text-zinc-300">Persentase menunjukkan jumlah kompetensi yang didukung bukti dibandingkan daftar kompetensi jalur karier. Bukti berasal dari sertifikat terverifikasi yang masih berlaku dan rubrik tugas terbaru yang memperoleh sedikitnya 70% skor maksimum.</p>
        <p class="max-w-prose text-sm leading-relaxed text-zinc-700 dark:text-zinc-300">Pencocokan memakai nama kompetensi dengan mengabaikan kapital dan spasi berlebih. Batas 70% digunakan untuk saran latihan, bukan penetapan kelulusan institusi. Persentase ini bukan peluang diterima kerja atau jaminan kelayakan profesi.</p>
    </section>

    @if ($this->profile['evidence'] === [])
        <div class="flex flex-col items-start gap-3 border-y border-zinc-200 py-6 dark:border-zinc-700">
            <flux:heading size="lg" level="2">Mulai membangun bukti kompetensi</flux:heading>
            <flux:text>Belum ada bukti yang memenuhi aturan rekomendasi. Jalur di bawah dapat menjadi acuan belajar; belum ada yang ditandai sebagai cocok. Ajukan sertifikat atau selesaikan tugas praktikum untuk memperbarui hasil.</flux:text>
        </div>
    @endif

    <div class="grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <section class="flex min-w-0 flex-col gap-6" aria-label="Jalur karier">
            @foreach ($this->profile['recommendations'] as $career)
                <article wire:key="career-{{ $career['key'] }}" class="flex flex-col gap-4 border-b border-zinc-200 pb-6 last:border-b-0 dark:border-zinc-700">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <flux:heading size="lg" level="2">{{ $career['title'] }}</flux:heading>
                            <flux:text class="mt-2">{{ $career['description'] }}</flux:text>
                        </div>
                        <span class="text-xl font-semibold tabular-nums">{{ $career['match_percentage'] }}<span class="text-sm font-normal">%</span></span>
                    </div>
                    <div class="flex flex-col gap-2">
                        <progress value="{{ $career['match_percentage'] }}" max="100" aria-label="Kecocokan kompetensi {{ $career['title'] }}" class="h-2 w-full accent-emerald-600 dark:accent-emerald-400">{{ $career['match_percentage'] }}%</progress>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ count($career['matched_skills']) }} dari {{ count($career['required_skills']) }} kompetensi didukung bukti.</p>
                    </div>
                    @if ($career['matched_skills'] !== [])
                        <div class="flex flex-col gap-2">
                            <h3 class="text-sm font-medium">Sudah didukung bukti</h3>
                            <div class="flex flex-wrap gap-2">@foreach ($career['matched_skills'] as $skill)<flux:badge wire:key="matched-{{ $career['key'] }}-{{ $loop->index }}" color="emerald" size="sm">{{ $skill }}</flux:badge>@endforeach</div>
                        </div>
                    @endif
                    @if ($career['missing_skills'] !== [])
                        <div class="flex flex-col gap-2">
                            <h3 class="text-sm font-medium">Lengkapi bukti atau latih berikutnya</h3>
                            <div class="flex flex-wrap gap-2">@foreach ($career['missing_skills'] as $skill)<flux:badge wire:key="missing-{{ $career['key'] }}-{{ $loop->index }}" size="sm">{{ $skill }}</flux:badge>@endforeach</div>
                        </div>
                    @endif
                </article>
            @endforeach
        </section>

        <aside class="flex min-w-0 flex-col gap-7">
            <section class="flex flex-col gap-4" aria-label="Bukti kompetensi">
                <flux:heading size="lg" level="2">Bukti yang digunakan</flux:heading>
                @forelse ($this->profile['evidence'] as $skill => $sources)
                    <details wire:key="career-evidence-{{ $loop->index }}" class="border-b border-zinc-200 pb-3 dark:border-zinc-700">
                        <summary class="cursor-pointer rounded-md py-1 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-4">{{ $skill }} <span class="font-normal text-zinc-600 dark:text-zinc-400">· {{ count($sources) }} bukti</span></summary>
                        <ul class="mt-3 flex flex-col gap-3">
                            @foreach ($sources as $source)
                                <li wire:key="career-source-{{ $skill }}-{{ $loop->index }}" class="flex flex-col gap-1 text-sm">
                                    <span class="break-words font-medium">{{ $source['title'] }}</span>
                                    <span class="text-zinc-600 dark:text-zinc-400">{{ $source['detail'] }}</span>
                                    @if ($source['source'] === 'certificate')
                                        <a href="{{ route('certificates.index', ['certificate' => $source['id']]) }}" wire:navigate class="w-fit rounded-sm text-blue-700 underline underline-offset-4 hover:text-blue-900 focus-visible:outline-2 focus-visible:outline-offset-4 dark:text-blue-300 dark:hover:text-blue-200">Lihat sertifikat</a>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @empty
                    <flux:text>Bukti dari sertifikat dan penilaian praktikum akan tampil di sini.</flux:text>
                @endforelse
            </section>
            @if ($this->profile['declared_skills'] !== [])
                <section class="flex flex-col gap-3 border-t border-zinc-200 pt-5 dark:border-zinc-700">
                    <flux:heading level="2">Tag dari portofolio</flux:heading>
                    <div class="flex flex-wrap gap-2">@foreach ($this->profile['declared_skills'] as $skill)<flux:badge wire:key="career-declared-{{ $loop->index }}" size="sm">{{ $skill }}</flux:badge>@endforeach</div>
                    <flux:text>Tag proyek merupakan pernyataan mandiri dan tidak menambah persentase kecocokan. Gunakan sertifikat atau penilaian praktikum sebagai bukti pendukung.</flux:text>
                </section>
            @endif
        </aside>
    </div>
</section>
