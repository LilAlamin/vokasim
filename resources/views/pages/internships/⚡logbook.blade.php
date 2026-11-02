<?php

use App\Actions\RecordInternshipAttendance;
use App\Actions\SaveInternshipLogbook;
use App\Enums\LogbookStatus;
use App\Models\InternshipAttendance;
use App\Models\InternshipLogbook;
use App\Models\InternshipPlacement;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('E-Logbook & Presensi')] class extends Component {
    use WithFileUploads;

    public int $placementId = 0;
    public string $month = '';
    public string $workDate = '';
    public string $description = '';
    public string $effectiveHours = '0';
    public $photo = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->isMahasiswa(), 403);
        $this->placementId = ($this->placements->first(fn ($placement) => $placement->isActive()) ?? $this->placements->first())?->id ?? 0;
        $this->updatedPlacementId();
    }

    public function updatedPlacementId(): void
    {
        unset($this->placement, $this->entry, $this->calendarLogbooks, $this->calendarAttendances, $this->todayAttendance);
        $today = CarbonImmutable::now($this->placement?->timezone ?? 'Asia/Jakarta');
        $this->month = $today->format('Y-m');
        $this->selectDay($today->toDateString());
    }

    public function updatedWorkDate(): void
    {
        $this->selectDay($this->workDate);
    }

    public function selectDay(string $date): void
    {
        Validator::make(['workDate' => $date], ['workDate' => ['required', 'date_format:Y-m-d']])->validate();
        $this->workDate = $date;
        $this->month = substr($date, 0, 7);
        unset($this->entry);
        $this->description = $this->entry?->description ?? '';
        $this->effectiveHours = (string) (($this->entry?->effective_minutes ?? 0) / 60);
        $this->photo = null;
        $this->resetValidation();
    }

    public function recordAttendance(string $type, array $coordinates, RecordInternshipAttendance $record): void
    {
        $this->resetValidation();
        $attendance = $record->handle(Auth::user(), $this->placementId, $type, $coordinates);
        unset($this->todayAttendance, $this->calendarAttendances, $this->selectedAttendance);
        Flux::toast(variant: 'success', text: $type === 'in' ? 'Presensi datang tercatat.' : 'Presensi pulang tercatat. Lengkapi logbook untuk tanggal '.$attendance->work_date->format('d M Y').'.');
    }

    public function saveDraft(SaveInternshipLogbook $save): void
    {
        $this->saveEntry($save, false);
    }

    public function send(SaveInternshipLogbook $save): void
    {
        $this->saveEntry($save, true);
    }

    private function saveEntry(SaveInternshipLogbook $save, bool $submit): void
    {
        $this->resetValidation();
        $entry = $save->handle(Auth::user(), $this->placementId, ['work_date' => $this->workDate, 'description' => $this->description, 'effective_hours' => $this->effectiveHours], $this->photo);
        $this->photo = null;
        unset($this->entry, $this->calendarLogbooks);
        if ($submit) {
            $save->submit(Auth::user(), $entry->id);
        }
        Flux::toast(variant: 'success', text: $submit ? 'Logbook dikirim ke mentor DUDI.' : 'Draft logbook tersimpan.');
    }

    #[Computed]
    public function placements(): \Illuminate\Database\Eloquent\Collection
    {
        abort_unless(Auth::user()->isMahasiswa(), 403);
        return InternshipPlacement::query()->forUser(Auth::user())->orderByDesc('starts_on')->get();
    }

    #[Computed]
    public function placement(): ?InternshipPlacement
    {
        if (! $this->placementId) {
            return null;
        }
        return InternshipPlacement::query()->forUser(Auth::user())->with(['mentor', 'lecturer'])->findOrFail($this->placementId);
    }

    #[Computed]
    public function entry(): ?InternshipLogbook
    {
        return $this->placement?->logbooks()->with('reviews.reviewer')->whereDate('work_date', $this->workDate)->first();
    }

    #[Computed]
    public function todayAttendance(): ?InternshipAttendance
    {
        if (! $this->placement) {
            return null;
        }
        return $this->placement->attendances()->whereNull('checked_out_at')->where('checked_in_at', '>=', CarbonImmutable::now('UTC')->subHours(24))->latest('checked_in_at')->first()
            ?? $this->placement->attendances()->whereDate('work_date', CarbonImmutable::now($this->placement->timezone)->toDateString())->first();
    }

    #[Computed]
    public function selectedAttendance(): ?InternshipAttendance
    {
        return $this->placement?->attendances()->whereDate('work_date', $this->workDate)->first();
    }

    #[Computed]
    public function monthStart(): CarbonImmutable
    {
        if (! Validator::make(['month' => $this->month], ['month' => ['required', 'date_format:Y-m']])->passes()) {
            return CarbonImmutable::now()->startOfMonth();
        }
        return CarbonImmutable::parse($this->month.'-01');
    }

    #[Computed]
    public function calendarLogbooks(): \Illuminate\Support\Collection
    {
        return $this->placement?->logbooks()->whereDate('work_date', '>=', $this->monthStart->toDateString())->whereDate('work_date', '<=', $this->monthStart->endOfMonth()->toDateString())->get()->keyBy(fn ($entry) => $entry->work_date->toDateString()) ?? collect();
    }

    #[Computed]
    public function calendarAttendances(): \Illuminate\Support\Collection
    {
        return $this->placement?->attendances()->whereDate('work_date', '>=', $this->monthStart->toDateString())->whereDate('work_date', '<=', $this->monthStart->endOfMonth()->toDateString())->orderByDesc('work_date')->get()->keyBy(fn ($attendance) => $attendance->work_date->toDateString()) ?? collect();
    }

    #[Computed]
    public function verifiedHours(): float
    {
        return $this->placement?->verifiedHours() ?? 0;
    }
}; ?>

<section class="mx-auto flex w-full max-w-6xl flex-col gap-6">
    <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
        <div>
            <flux:heading size="xl" level="1">E-Logbook & Presensi</flux:heading>
            <flux:text class="mt-2">Catat kehadiran, dokumentasikan pekerjaan, dan pantau verifikasi pembimbing.</flux:text>
        </div>
        @if ($this->placements->isNotEmpty())
            <flux:select wire:model.live="placementId" label="Penempatan magang" class="sm:max-w-xs">
                @foreach ($this->placements as $placement)
                    <option wire:key="option-{{ $placement->id }}" value="{{ $placement->id }}">{{ $placement->company_name }} · {{ $placement->starts_on->format('M Y') }}</option>
                @endforeach
            </flux:select>
        @endif
    </header>

    @if ($errors->any())
        <div role="alert" class="rounded-lg bg-red-50 p-4 text-sm text-red-800 dark:bg-red-950 dark:text-red-200">
            <ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @if (! $this->placement)
        <div class="rounded-xl border border-dashed border-zinc-300 p-8 dark:border-zinc-700">
            <flux:heading level="2">Penempatan magang belum tersedia</flux:heading>
            <flux:text class="mt-2">Hubungi administrator untuk menentukan mitra DUDI, lokasi kerja, mentor, dan dosen pembimbing. Setelah itu, presensi dan logbook dapat digunakan.</flux:text>
        </div>
    @else
        <div class="rounded-xl border border-zinc-200 p-5 sm:p-6 dark:border-zinc-700" x-data="internshipGeolocation">
            <div class="flex flex-col justify-between gap-4 md:flex-row">
                <div class="min-w-0 space-y-2">
                    <flux:heading size="lg" level="2">{{ $this->placement->company_name }}</flux:heading>
                    <flux:text>{{ $this->placement->address }}</flux:text>
                    <flux:text>{{ $this->placement->starts_on->format('d M Y') }} – {{ $this->placement->ends_on->format('d M Y') }} · {{ $this->placement->timezone }} · Radius {{ $this->placement->radius_meters }} m</flux:text>
                    <flux:text>Mentor: {{ $this->placement->mentor->name }} · Dosen: {{ $this->placement->lecturer->name }}</flux:text>
                </div>
                <div class="shrink-0 space-y-2 md:text-right">
                    <flux:heading>{{ number_format($this->verifiedHours, 2, ',', '.') }} / {{ $this->placement->target_hours }} jam</flux:heading>
                    <flux:text>Terverifikasi dosen · seluruh periode</flux:text>
                </div>
            </div>
            <flux:separator class="my-5" />
            <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                <div class="space-y-1">
                    <flux:heading level="3">Presensi {{ $this->todayAttendance?->work_date->format('d M Y') ?? CarbonImmutable::now($this->placement->timezone)->format('d M Y') }}</flux:heading>
                    @if ($this->todayAttendance)
                        <flux:text>Datang {{ $this->todayAttendance->checked_in_at->setTimezone($this->placement->timezone)->format('H:i') }} · {{ $this->todayAttendance->checked_out_at ? 'Pulang '.$this->todayAttendance->checked_out_at->setTimezone($this->placement->timezone)->format('H:i') : 'Belum presensi pulang' }}</flux:text>
                    @else
                        <flux:text>{{ $this->placement->isActive() ? 'Belum ada presensi hari ini. Izinkan akses GPS saat diminta.' : 'Presensi tersedia saat periode magang aktif.' }}</flux:text>
                    @endif
                </div>
                <div class="flex flex-wrap gap-3">
                    @if (! $this->todayAttendance && $this->placement->isActive())
                        <flux:button variant="primary" x-on:click="record('in')" x-bind:disabled="busy" icon="map-pin">Presensi datang</flux:button>
                    @elseif ($this->todayAttendance && ! $this->todayAttendance->checked_out_at)
                        <flux:button variant="primary" x-on:click="record('out')" x-bind:disabled="busy" icon="map-pin">Presensi pulang</flux:button>
                    @elseif ($this->todayAttendance?->checked_out_at)
                        <flux:badge color="emerald">Presensi selesai</flux:badge>
                    @endif
                </div>
            </div>
            <p x-cloak x-show="busy" role="status" class="mt-3 text-sm text-zinc-600 dark:text-zinc-300">Mengambil lokasi dan mencatat presensi…</p>
            <p x-cloak x-show="error" x-text="error" role="alert" class="mt-3 text-sm text-red-700 dark:text-red-300"></p>
        </div>

        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
            <div class="min-w-0 space-y-6">
                <div class="rounded-xl border border-zinc-200 p-4 sm:p-5 dark:border-zinc-700">
                    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
                        <flux:heading size="lg" level="2">Kalender aktivitas</flux:heading>
                        <flux:input type="month" wire:model.live="month" label="Bulan" class="max-w-44" />
                    </div>
                    <div class="grid grid-cols-7 gap-1 text-center text-sm">
                        @foreach (['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $dayName)
                            <span wire:key="weekday-{{ $dayName }}" class="py-2 text-xs font-medium text-zinc-600 dark:text-zinc-400">{{ $dayName }}</span>
                        @endforeach
                        @for ($pad = 1; $pad < $this->monthStart->isoWeekday(); $pad++)<span aria-hidden="true"></span>@endfor
                        @for ($day = 1; $day <= $this->monthStart->daysInMonth; $day++)
                            @php
                                $date = $this->monthStart->day($day)->toDateString();
                                $dailyEntry = $this->calendarLogbooks->get($date);
                                $dailyAttendance = $this->calendarAttendances->get($date);
                                $dayLabel = $dailyEntry?->status->label() ?? ($dailyAttendance ? 'Presensi tercatat' : 'Belum ada aktivitas');
                            @endphp
                            <button type="button" wire:key="day-{{ $date }}" wire:click="selectDay('{{ $date }}')" aria-label="{{ $date }}: {{ $dayLabel }}" aria-pressed="{{ $date === $workDate ? 'true' : 'false' }}" title="{{ $dayLabel }}" @class(['flex min-h-12 flex-col items-center justify-center gap-1 rounded-lg border focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600', 'border-blue-600 bg-blue-50 font-semibold text-blue-800 dark:bg-blue-950 dark:text-blue-200' => $date === $workDate, 'border-transparent hover:bg-zinc-100 dark:hover:bg-zinc-800' => $date !== $workDate])>
                                {{ $day }}
                                <span @class(['size-1.5 rounded-full', 'bg-emerald-600' => $dailyEntry?->status === LogbookStatus::Verified, 'bg-blue-600' => $dailyEntry?->status === LogbookStatus::MentorApproved, 'bg-amber-500' => $dailyEntry?->status === LogbookStatus::PendingMentor, 'bg-zinc-500' => $dailyEntry?->status === LogbookStatus::Draft, 'bg-zinc-300 dark:bg-zinc-600' => ! $dailyEntry && $dailyAttendance, 'bg-transparent' => ! $dailyEntry && ! $dailyAttendance])></span>
                            </button>
                        @endfor
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach (LogbookStatus::cases() as $status)<flux:badge wire:key="legend-{{ $status->value }}" :color="$status->color()" size="sm">{{ $status->label() }}</flux:badge>@endforeach
                    </div>
                    <flux:text class="mt-3">Pilih tanggal untuk membuka aktivitas dan riwayat validasinya.</flux:text>
                </div>

                <div class="space-y-3">
                    <flux:heading level="2">Riwayat presensi bulan ini</flux:heading>
                    @forelse ($this->calendarAttendances as $attendance)
                        <button type="button" wire:key="attendance-{{ $attendance->id }}" wire:click="selectDay('{{ $attendance->work_date->toDateString() }}')" class="flex w-full items-center justify-between gap-3 rounded-lg border border-zinc-200 p-3 text-left text-sm hover:bg-zinc-50 focus-visible:outline-2 focus-visible:outline-blue-600 dark:border-zinc-700 dark:hover:bg-zinc-800">
                            <span>{{ $attendance->work_date->format('d M') }} · {{ $attendance->checked_in_at->setTimezone($this->placement->timezone)->format('H:i') }}–{{ $attendance->checked_out_at?->setTimezone($this->placement->timezone)->format('H:i') ?? '…' }}</span>
                            <span class="text-zinc-600 dark:text-zinc-300">{{ $attendance->checked_out_at ? number_format($attendance->elapsedMinutes() / 60, 2).' jam' : 'Belum pulang' }}</span>
                        </button>
                    @empty
                        <flux:text>Belum ada presensi pada bulan ini.</flux:text>
                    @endforelse
                </div>
            </div>

            <div class="min-w-0 rounded-xl border border-zinc-200 p-5 sm:p-6 dark:border-zinc-700">
                <flux:heading size="lg" level="2">Aktivitas {{ $workDate }}</flux:heading>
                <div class="mt-3"><x-internship-attendance :attendance="$this->selectedAttendance" :timezone="$this->placement->timezone" /></div>
                @if (! $this->entry || $this->entry->status === LogbookStatus::Draft)
                    <form wire:submit="saveDraft" class="mt-5 space-y-5">
                        <flux:input wire:model.live="workDate" name="work_date" label="Tanggal aktivitas" type="date" required />
                        <flux:textarea wire:model="description" label="Aktivitas teknis" rows="6" placeholder="Tuliskan pekerjaan, alat yang digunakan, hasil, dan kendala yang ditemui." maxlength="10000" />
                        <flux:input wire:model="effectiveHours" name="effective_hours" label="Jam kerja efektif" description="Kurangi waktu istirahat. Gunakan kelipatan 0,25 jam (15 menit)." type="number" step="0.25" min="0" max="24" required />
                        <flux:input wire:model="photo" label="Foto dokumentasi" description="JPG, PNG, atau WebP, maksimal 5 MB. Foto wajib saat mengirim." type="file" accept="image/jpeg,image/png,image/webp" />
                        <p wire:loading wire:target="photo" role="status" class="text-sm text-zinc-600 dark:text-zinc-300">Mengunggah foto…</p>
                        @if ($this->entry?->photo_path)
                            <a href="{{ route('internships.photo', $this->entry) }}" target="_blank" rel="noopener" class="inline-block text-sm text-blue-700 underline underline-offset-4 dark:text-blue-300">Lihat foto tersimpan</a>
                        @endif
                        <div class="flex flex-wrap gap-3">
                            <flux:button type="submit" wire:loading.attr="disabled" wire:target="photo,send,saveDraft">Simpan draft</flux:button>
                            <flux:button type="button" variant="primary" wire:click="send" wire:loading.attr="disabled" wire:target="photo,send,saveDraft">Simpan & kirim ke mentor</flux:button>
                        </div>
                        <flux:text>Pengiriman memerlukan presensi datang–pulang dan foto. Logbook terkunci selama proses verifikasi.</flux:text>
                    </form>
                @endif
                @if ($this->entry)
                    <div class="mt-6"><x-internship-logbook-detail :logbook="$this->entry" :show-description="$this->entry->status !== LogbookStatus::Draft" /></div>
                @endif
            </div>
        </div>
    @endif
</section>
