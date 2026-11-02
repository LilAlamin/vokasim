<?php

use App\Actions\BuildPartnerAnalytics;
use App\Actions\BuildTracerAnalytics;
use App\Models\TracerResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Tracer Study & Pasar Kerja')] class extends Component
{
    #[Url]
    public string $graduationYear = '';

    #[Url]
    public string $studyProgram = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', TracerResponse::class);
    }

    #[Computed]
    public function analytics(): array
    {
        return app(BuildTracerAnalytics::class)->handle(Auth::user(), ['graduation_year' => $this->graduationYear, 'study_program' => $this->studyProgram]);
    }

    #[Computed]
    public function partners(): array
    {
        return app(BuildPartnerAnalytics::class)->handle(Auth::user());
    }

    #[Computed]
    public function programs(): \Illuminate\Support\Collection
    {
        Gate::authorize('viewAny', TracerResponse::class);

        return TracerResponse::query()->whereNotNull('study_program')->distinct()->orderBy('study_program')->pluck('study_program');
    }

    #[Computed]
    public function years(): \Illuminate\Support\Collection
    {
        Gate::authorize('viewAny', TracerResponse::class);

        return TracerResponse::query()->distinct()->orderByDesc('graduation_year')->pluck('graduation_year');
    }

    #[Computed]
    public function map(): array
    {
        $locations = $this->partners['locations'];

        if ($locations === []) {
            return ['points' => [], 'west' => 0, 'east' => 0, 'north' => 0, 'south' => 0];
        }

        $west = max(-180, min(array_column($locations, 'longitude')) - 1);
        $east = min(180, max(array_column($locations, 'longitude')) + 1);
        $south = max(-90, min(array_column($locations, 'latitude')) - 1);
        $north = min(90, max(array_column($locations, 'latitude')) + 1);

        return [
            'west' => $west, 'east' => $east, 'south' => $south, 'north' => $north,
            'points' => array_map(fn (array $location): array => [
                ...$location,
                'x' => 48 + (($location['longitude'] - $west) / ($east - $west) * 704),
                'y' => 30 + (($north - $location['latitude']) / ($north - $south) * 240),
            ], $locations),
        ];
    }
}; ?>

<section class="mx-auto flex w-full max-w-6xl flex-col gap-8">
    <header><flux:heading size="xl" level="1">Tracer Study & Pasar Kerja</flux:heading><flux:text class="mt-2">Gambaran karier berdasarkan jawaban alumni yang tersimpan. Filter berlaku untuk hasil survei; sebaran kemitraan menunjukkan seluruh mitra aktif.</flux:text></header>

    <div class="grid gap-4 sm:max-w-2xl sm:grid-cols-2">
        <flux:select wire:model.live="graduationYear" label="Tahun kelulusan"><option value="">Semua tahun</option>@foreach ($this->years as $year)<option wire:key="cohort-{{ $year }}" value="{{ $year }}">{{ $year }}</option>@endforeach</flux:select>
        <flux:select wire:model.live="studyProgram" label="Program studi"><option value="">Semua program studi</option>@foreach ($this->programs as $program)<option wire:key="program-{{ md5($program) }}" value="{{ $program }}">{{ $program }}</option>@endforeach</flux:select>
    </div>
    @if ($errors->any())<div role="alert" class="rounded-lg bg-red-50 p-4 text-sm text-red-800 dark:bg-red-950 dark:text-red-200"><ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li wire:key="analytics-error-{{ $loop->index }}">{{ $error }}</li>@endforeach</ul></div>@endif

    @if ($this->analytics['total_responses'] === 0)
        <div class="border-y border-dashed border-zinc-300 py-8 dark:border-zinc-700"><flux:heading size="lg" level="2">Belum ada jawaban pada kelompok ini</flux:heading><flux:text class="mt-2">Alumni dapat mengisi Survei Alumni melalui akun mahasiswa. Ubah filter untuk melihat kelompok lain.</flux:text></div>
    @else
        <section class="flex flex-col gap-4">
            <div><flux:heading size="lg" level="2">Hasil dari {{ $this->analytics['total_responses'] }} responden</flux:heading><flux:text class="mt-1">Persentase menggambarkan responden survei. Rata-rata hanya memasukkan jawaban yang tersedia.</flux:text></div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-120 text-left text-sm">
                    <caption class="sr-only">Indikator tracer study dengan jumlah jawaban yang menjadi dasar perhitungan</caption>
                    <thead class="border-b border-zinc-200 text-zinc-600 dark:border-zinc-700 dark:text-zinc-300"><tr><th scope="col" class="py-3 pr-5 font-medium">Indikator</th><th scope="col" class="py-3 pr-5 text-right font-medium">Hasil</th><th scope="col" class="py-3 text-right font-medium">Dasar perhitungan</th></tr></thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        <tr><th scope="row" class="py-4 pr-5 font-medium">Bekerja / berwirausaha</th><td class="py-4 pr-5 text-right tabular-nums">{{ $this->analytics['absorption_percentage'] }}%</td><td class="py-4 text-right">{{ $this->analytics['working_count'] }} dari {{ $this->analytics['total_responses'] }} responden</td></tr>
                        <tr><th scope="row" class="py-4 pr-5 font-medium">Rata-rata waktu tunggu kerja</th><td class="py-4 pr-5 text-right tabular-nums">{{ $this->analytics['average_wait_months'] !== null ? $this->analytics['average_wait_months'].' bulan' : 'Belum tersedia' }}</td><td class="py-4 text-right">{{ $this->analytics['wait_sample'] }} jawaban tanggal kerja</td></tr>
                        <tr><th scope="row" class="py-4 pr-5 font-medium">Pekerjaan sesuai bidang studi</th><td class="py-4 pr-5 text-right tabular-nums">{{ $this->analytics['alignment_percentage'] !== null ? $this->analytics['alignment_percentage'].'%' : 'Belum tersedia' }}</td><td class="py-4 text-right">{{ $this->analytics['alignment_sample'] }} penilaian keselarasan</td></tr>
                        <tr><th scope="row" class="py-4 pr-5 font-medium">Rata-rata gaji pertama / bulan</th><td class="py-4 pr-5 text-right tabular-nums">{{ $this->analytics['average_first_salary'] !== null ? 'Rp '.number_format($this->analytics['average_first_salary'], 0, ',', '.') : 'Belum tersedia' }}</td><td class="py-4 text-right">{{ $this->analytics['salary_sample'] }} jawaban gaji</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div class="grid gap-8 lg:grid-cols-2">
            <section class="flex flex-col gap-4">
                <flux:heading size="lg" level="2">Status kegiatan alumni</flux:heading>
                @foreach (TracerResponse::employmentStatuses() as $status => $label)
                    <div wire:key="status-breakdown-{{ $status }}" class="flex flex-col gap-2">
                        <div class="flex justify-between gap-3 text-sm"><span>{{ $label }}</span><span class="tabular-nums">{{ $this->analytics['status_counts'][$status] }} responden</span></div>
                        <progress value="{{ $this->analytics['status_counts'][$status] }}" max="{{ $this->analytics['total_responses'] }}" aria-label="{{ $label }}" class="h-2 w-full accent-blue-600"></progress>
                    </div>
                @endforeach
            </section>
            <section class="flex flex-col gap-4">
                <flux:heading size="lg" level="2">Keahlian yang perlu ditingkatkan</flux:heading>
                <flux:text>Sepuluh keahlian paling sering dilaporkan responden pada kelompok ini.</flux:text>
                @forelse ($this->analytics['skill_gaps'] as $skill => $count)
                    <div wire:key="skill-gap-{{ md5($skill) }}" class="flex justify-between gap-4 border-b border-zinc-200 pb-3 text-sm dark:border-zinc-700"><span class="break-words">{{ $skill }}</span><span class="shrink-0 tabular-nums">{{ $count }} responden</span></div>
                @empty
                    <flux:text>Belum ada kebutuhan keahlian yang dilaporkan.</flux:text>
                @endforelse
            </section>
        </div>
        <section class="flex flex-col gap-4">
            <flux:heading size="lg" level="2">Sebaran tahun kelulusan responden</flux:heading>
            <dl class="flex flex-wrap gap-x-8 gap-y-4">@foreach ($this->analytics['graduation_year_counts'] as $year => $count)<div wire:key="year-count-{{ $year }}"><dt class="text-sm text-zinc-600 dark:text-zinc-300">{{ $year }}</dt><dd class="mt-1 font-medium tabular-nums">{{ $count }} responden</dd></div>@endforeach</dl>
        </section>
    @endif

    <section class="flex flex-col gap-5 border-t border-zinc-200 pt-7 dark:border-zinc-700">
        <div class="flex flex-wrap items-start justify-between gap-4"><div><flux:heading size="lg" level="2">Sebaran kemitraan industri</flux:heading><flux:text class="mt-2">{{ $this->partners['active_companies'] }} mitra aktif · {{ $this->partners['current_placements'] }} penempatan hari ini · kuota {{ $this->partners['total_quota'] }} mahasiswa</flux:text></div><flux:button :href="route('partners.index')" wire:navigate>Kelola kemitraan</flux:button></div>
        <flux:text>{{ $this->partners['expiring_agreements'] }} perjanjian berakhir dalam 30 hari · {{ $this->partners['expired_agreements'] }} perjanjian melewati masa berlaku.</flux:text>
        @if ($this->map['points'] !== [])
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <svg viewBox="0 0 800 310" role="img" aria-label="Sebaran koordinat perusahaan mitra aktif; rincian tersedia pada tabel lokasi" class="w-full text-zinc-400">
                    <path d="M48 30V270H752" fill="none" stroke="currentColor" stroke-width="1" />
                    <text x="48" y="295" class="fill-zinc-600 text-xs dark:fill-zinc-300">{{ number_format($this->map['west'], 1) }}°</text>
                    <text x="752" y="295" text-anchor="end" class="fill-zinc-600 text-xs dark:fill-zinc-300">{{ number_format($this->map['east'], 1) }}° bujur</text>
                    <text x="38" y="35" text-anchor="end" class="fill-zinc-600 text-xs dark:fill-zinc-300">{{ number_format($this->map['north'], 1) }}°</text>
                    <text x="38" y="270" text-anchor="end" class="fill-zinc-600 text-xs dark:fill-zinc-300">{{ number_format($this->map['south'], 1) }}°</text>
                    @foreach ($this->map['points'] as $point)<circle wire:key="partner-location-{{ $point['id'] }}" cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="6" class="fill-blue-600 dark:fill-blue-400"><title>{{ $point['name'] }} · {{ $point['city'] }} · {{ $point['latitude'] }}, {{ $point['longitude'] }}</title></circle>@endforeach
                </svg>
                <flux:text>Setiap titik adalah koordinat satu mitra aktif. Arah atas menunjukkan utara.</flux:text>
            </div>
        @endif
        @if ($this->partners['unmapped_companies'] > 0)<flux:text>{{ $this->partners['unmapped_companies'] }} mitra belum memiliki koordinat. Lengkapi profil perusahaan agar lokasi dapat ditampilkan.</flux:text>@endif
        <div class="grid gap-7 lg:grid-cols-[1fr_2fr]">
            <dl class="flex flex-col gap-3">@forelse ($this->partners['regions'] as $region => $count)<div wire:key="region-{{ md5($region) }}" class="flex justify-between gap-4 text-sm"><dt>{{ $region }}</dt><dd class="shrink-0 tabular-nums">{{ $count }} mitra</dd></div>@empty<flux:text>Belum ada mitra aktif. Tambahkan profil perusahaan melalui Manajemen Kemitraan.</flux:text>@endforelse</dl>
            @if ($this->partners['locations'] !== [])
                <div class="overflow-x-auto"><table class="w-full text-left text-sm"><caption class="sr-only">Lokasi perusahaan mitra dan jumlah penempatan saat ini</caption><thead class="text-zinc-600 dark:text-zinc-300"><tr><th scope="col" class="pb-3 pr-4 font-medium">Perusahaan / kota</th><th scope="col" class="pb-3 pr-4 font-medium">Lintang, bujur</th><th scope="col" class="pb-3 text-right font-medium">Penempatan</th></tr></thead><tbody>@foreach ($this->partners['locations'] as $location)<tr wire:key="location-table-{{ $location['id'] }}" class="border-t border-zinc-200 dark:border-zinc-700"><th scope="row" class="py-3 pr-4 font-medium">{{ $location['name'] }}<span class="mt-1 block font-normal text-zinc-600 dark:text-zinc-300">{{ $location['city'] }}</span></th><td class="py-3 pr-4 tabular-nums">{{ number_format($location['latitude'], 4) }}, {{ number_format($location['longitude'], 4) }}</td><td class="py-3 text-right tabular-nums">{{ $location['placements'] }}</td></tr>@endforeach</tbody></table></div>
            @endif
        </div>
    </section>
</section>
