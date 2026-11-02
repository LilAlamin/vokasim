<?php

use App\Actions\ReviewInternshipLogbook;
use App\Enums\LogbookStatus;
use App\Models\InternshipAttendance;
use App\Models\InternshipLogbook;
use App\Models\InternshipPlacement;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Validasi Logbook Magang')] class extends Component {
    use WithPagination;

    #[Url(keep: true)]
    public string $status = '';

    #[Url(except: '')]
    public string $month = '';

    #[Url(except: '')]
    public string $placementFilter = '';

    #[Locked]
    public ?int $selectedId = null;

    public string $feedback = '';

    /** @var array<array-key, mixed> */
    public array $selectedLogbookIds = [];

    public function mount(): void
    {
        $this->authorizeReviewer();

        if (! request()->query->has('status')) {
            $this->status = Auth::user()->isMentorDudi() ? LogbookStatus::PendingMentor->value : LogbookStatus::MentorApproved->value;
        }
    }

    private function authorizeReviewer(): void
    {
        abort_unless(Auth::user()->isMentorDudi() || Auth::user()->isDosen(), 403);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['status', 'month', 'placementFilter'], true)) {
            $this->resetPage();
        }
    }

    public function updatedPaginators(int $page, string $pageName): void
    {
        $this->selectedLogbookIds = [];
        $this->selectedId = null;
        $this->feedback = '';
        $this->resetValidation();
        unset($this->selected, $this->attendance, $this->entries, $this->hasReviewableEntries);
    }

    public function selectLogbook(int $id): void
    {
        $this->authorizeReviewer();
        $entry = InternshipLogbook::query()->forUser(Auth::user())->findOrFail($id);
        $this->selectedId = $entry->id;
        $this->feedback = '';
        $this->resetValidation();
        unset($this->selected);
    }

    public function review(string $decision, ReviewInternshipLogbook $review): void
    {
        $this->authorizeReviewer();
        abort_unless($this->selectedId, 404);
        $this->resetValidation();
        $review->handle(Auth::user(), $this->selectedId, $decision, $this->feedback);
        $this->feedback = '';
        $this->selectedLogbookIds = [];
        unset($this->selected, $this->entries, $this->placements, $this->hasReviewableEntries);
        Flux::toast(variant: 'success', text: $decision === 'revise' ? 'Logbook dikembalikan dengan catatan revisi.' : 'Validasi logbook tersimpan.');
    }

    public function approveSelected(ReviewInternshipLogbook $review): void
    {
        $this->authorizeReviewer();
        $this->resetValidation();
        $approvedCount = $review->approveMany(Auth::user(), $this->selectedLogbookIds);
        $this->resetPage();
        unset($this->placements);

        Flux::toast(variant: 'success', text: $approvedCount.' logbook '.(Auth::user()->isMentorDudi() ? 'disetujui mentor.' : 'terverifikasi dosen.'));
    }

    #[Computed]
    public function placements(): \Illuminate\Database\Eloquent\Collection
    {
        $this->authorizeReviewer();
        return InternshipPlacement::query()->forUser(Auth::user())->with('student')
            ->withSum(['logbooks as verified_minutes' => fn ($query) => $query->where('status', LogbookStatus::Verified)], 'effective_minutes')
            ->withSum(['logbooks as mentor_minutes' => fn ($query) => $query->whereIn('status', [LogbookStatus::MentorApproved, LogbookStatus::Verified])], 'effective_minutes')
            ->orderByDesc('starts_on')->get();
    }

    #[Computed]
    public function entries(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $this->authorizeReviewer();
        $query = InternshipLogbook::query()->forUser(Auth::user())->with(['placement.student']);
        if ($this->status !== '') {
            $query->where('status', $this->status);
        }
        if ($this->placementFilter !== '') {
            $query->where('internship_placement_id', $this->placementFilter);
        }
        if ($this->month !== '' && Validator::make(['month' => $this->month], ['month' => ['date_format:Y-m']])->passes()) {
            $start = CarbonImmutable::parse($this->month.'-01');
            $query->whereDate('work_date', '>=', $start->toDateString())->whereDate('work_date', '<=', $start->endOfMonth()->toDateString());
        }
        return $query->orderBy('work_date')->orderBy('id')->paginate(12);
    }

    #[Computed]
    public function hasReviewableEntries(): bool
    {
        return $this->entries->getCollection()->contains(
            fn (InternshipLogbook $entry): bool => Auth::user()->can('review', $entry),
        );
    }

    #[Computed]
    public function selected(): ?InternshipLogbook
    {
        $this->authorizeReviewer();
        return $this->selectedId ? InternshipLogbook::query()->forUser(Auth::user())->with(['placement.student', 'reviews.reviewer'])->findOrFail($this->selectedId) : null;
    }

    #[Computed]
    public function attendance(): ?InternshipAttendance
    {
        return $this->selected?->placement->attendances()->whereDate('work_date', $this->selected->work_date->toDateString())->first();
    }
}; ?>

<section class="mx-auto flex w-full max-w-6xl flex-col gap-6">
    <header>
        <flux:heading size="xl" level="1">{{ auth()->user()->isMentorDudi() ? 'Validasi Aktivitas Magang' : 'Verifikasi Logbook Bimbingan' }}</flux:heading>
        <flux:text class="mt-2">{{ auth()->user()->isMentorDudi() ? 'Periksa pekerjaan, bukti foto, jam efektif, dan presensi GPS mahasiswa dampingan.' : 'Tinjau logbook yang telah disetujui mentor dan verifikasi capaian jam magang.' }}</flux:text>
    </header>
    @if ($errors->any())
        <div role="alert" class="rounded-lg bg-red-50 p-4 text-sm text-red-800 dark:bg-red-950 dark:text-red-200"><ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
        <flux:heading level="2">Rekap mahasiswa bimbingan · seluruh periode</flux:heading>
        <div class="mt-4 max-h-72 overflow-auto">
            <table class="w-full text-left text-sm">
                <caption class="sr-only">Jam kerja yang disetujui mentor dan terverifikasi dosen</caption>
                <thead class="text-zinc-600 dark:text-zinc-400"><tr><th scope="col" class="pb-3 pr-4 font-medium">Mahasiswa / mitra</th><th scope="col" class="pb-3 pr-4 text-right font-medium">Disetujui mentor</th><th scope="col" class="pb-3 text-right font-medium">Terverifikasi / target</th></tr></thead>
                <tbody>
                    @forelse ($this->placements as $placement)
                        <tr wire:key="summary-{{ $placement->id }}" class="border-t border-zinc-200 dark:border-zinc-700">
                            <th scope="row" class="py-3 pr-4 font-medium">{{ $placement->student->name }}<span class="mt-1 block text-xs font-normal text-zinc-600 dark:text-zinc-400">{{ $placement->company_name }}</span></th>
                            <td class="py-3 pr-4 text-right tabular-nums">{{ number_format(($placement->mentor_minutes ?? 0) / 60, 2) }} jam</td>
                            <td class="py-3 text-right tabular-nums">{{ number_format(($placement->verified_minutes ?? 0) / 60, 2) }} / {{ $placement->target_hours }} jam</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-4 text-zinc-600 dark:text-zinc-400">Belum ada mahasiswa dampingan. Administrator perlu menghubungkan akun Anda pada penempatan magang.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid items-start gap-6 lg:grid-cols-2">
        <div class="min-w-0 space-y-4">
            <flux:heading size="lg" level="2">Antrean & riwayat</flux:heading>
            <div class="grid gap-3 sm:grid-cols-2">
                <flux:select wire:model.live="status" label="Status">
                    <option value="">Semua status</option>
                    @foreach (LogbookStatus::cases() as $option)<option wire:key="status-{{ $option->value }}" value="{{ $option->value }}">{{ $option->label() }}</option>@endforeach
                </flux:select>
                <flux:input wire:model.live="month" label="Bulan (opsional)" type="month" />
                <flux:select wire:model.live="placementFilter" label="Mahasiswa / penempatan" class="sm:col-span-2">
                    <option value="">Semua mahasiswa dampingan</option>
                    @foreach ($this->placements as $placement)<option wire:key="filter-{{ $placement->id }}" value="{{ $placement->id }}">{{ $placement->student->name }} · {{ $placement->company_name }}</option>@endforeach
                </flux:select>
            </div>
            <flux:checkbox.group wire:model.live="selectedLogbookIds" class="space-y-3" wire:loading.attr="disabled" wire:target="approveSelected,review">
                @if ($this->hasReviewableEntries)
                    <div class="space-y-3 border-y border-zinc-200 py-4 dark:border-zinc-700">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <flux:checkbox.all label="Pilih halaman ini" />
                            <span role="status" class="text-sm text-zinc-600 dark:text-zinc-300">{{ count($selectedLogbookIds) }} dipilih</span>
                        </div>
                        <flux:text>Periksa aktivitas, presensi, dan foto sebelum memvalidasi pilihan. Pilihan dibersihkan saat filter atau halaman berganti.</flux:text>
                        <div class="flex flex-wrap gap-3">
                            <flux:button type="button" wire:click="approveSelected" variant="primary" :disabled="$selectedLogbookIds === []" wire:loading.attr="disabled" wire:target="approveSelected,review">
                                {{ auth()->user()->isMentorDudi() ? 'Setujui pilihan' : 'Verifikasi pilihan' }} ({{ count($selectedLogbookIds) }})
                            </flux:button>
                            @if ($selectedLogbookIds !== [])
                                <flux:button type="button" wire:click="$set('selectedLogbookIds', [])" wire:loading.attr="disabled" wire:target="approveSelected,review">Batal pilih</flux:button>
                            @endif
                        </div>
                        <p wire:loading wire:target="approveSelected" role="status" class="text-sm text-zinc-600 dark:text-zinc-300">Menyimpan validasi logbook…</p>
                    </div>
                @endif

                @forelse ($this->entries as $entry)
                    <div wire:key="entry-{{ $entry->id }}" @class(['flex items-start gap-3 rounded-xl border p-4', 'border-blue-600 bg-blue-50 dark:bg-blue-950' => $selectedId === $entry->id, 'border-zinc-200 dark:border-zinc-700' => $selectedId !== $entry->id])>
                        @can('review', $entry)
                            <flux:checkbox :value="(string) $entry->id" :aria-label="'Pilih logbook '.$entry->placement->student->name.' tanggal '.$entry->work_date->format('d M Y')" class="mt-1" />
                        @endcan
                        <button type="button" wire:click="selectLogbook({{ $entry->id }})" aria-pressed="{{ $selectedId === $entry->id ? 'true' : 'false' }}" wire:loading.attr="disabled" wire:target="approveSelected,review" class="flex min-w-0 flex-1 flex-col gap-3 rounded-md text-left hover:opacity-80 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-blue-600">
                            <span class="flex w-full flex-wrap items-center justify-between gap-2"><span class="break-words font-medium">{{ $entry->placement->student->name }}</span><span class="text-sm">{{ $entry->work_date->format('d M Y') }}</span></span>
                            <span class="line-clamp-2 break-words text-sm text-zinc-600 dark:text-zinc-300">{{ $entry->description ?: 'Draft belum dilengkapi' }}</span>
                            <span class="flex w-full flex-wrap items-center justify-between gap-2"><flux:badge :color="$entry->status->color()" size="sm">{{ $entry->status->label() }}</flux:badge><span class="text-sm tabular-nums">{{ number_format($entry->effective_minutes / 60, 2) }} jam efektif</span></span>
                        </button>
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-zinc-300 p-6 dark:border-zinc-700"><flux:heading level="3">Tidak ada logbook pada filter ini</flux:heading><flux:text class="mt-2">Logbook yang siap ditinjau akan muncul di sini. Pilih semua status untuk melihat riwayat.</flux:text></div>
                @endforelse
            </flux:checkbox.group>
            {{ $this->entries->links() }}
        </div>

        <div class="min-w-0 rounded-xl border border-zinc-200 p-5 sm:p-6 dark:border-zinc-700">
            @if ($this->selected)
                <div class="space-y-2">
                    <flux:heading size="lg" level="2">{{ $this->selected->placement->student->name }}</flux:heading>
                    <flux:text>{{ $this->selected->placement->company_name }} · {{ $this->selected->work_date->format('d M Y') }}</flux:text>
                </div>
                <div class="my-5"><x-internship-attendance :attendance="$this->attendance" :timezone="$this->selected->placement->timezone" /></div>
                <x-internship-logbook-detail :logbook="$this->selected" />
                @can('review', $this->selected)
                    <form wire:submit="review('approve')" class="mt-6 space-y-4 border-t border-zinc-200 pt-5 dark:border-zinc-700">
                        <flux:textarea wire:model="feedback" label="Catatan pembimbing" description="Wajib diisi jika logbook perlu direvisi." rows="3" maxlength="2000" />
                        <div class="flex flex-wrap gap-3">
                            <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="review,approveSelected">{{ auth()->user()->isMentorDudi() ? 'Setujui jam & aktivitas' : 'Verifikasi akhir logbook' }}</flux:button>
                            <flux:button type="button" wire:click="review('revise')" wire:loading.attr="disabled" wire:target="review,approveSelected">Kembalikan untuk revisi</flux:button>
                        </div>
                    </form>
                @else
                    <flux:text class="mt-6">{{ $this->selected->status === LogbookStatus::Verified ? 'Logbook sudah terverifikasi dan terkunci.' : 'Logbook belum menunggu keputusan Anda.' }}</flux:text>
                @endcan
            @else
                <flux:heading level="2">Pilih logbook untuk ditinjau</flux:heading>
                <flux:text class="mt-2">Detail aktivitas, foto, hasil pemeriksaan GPS, dan riwayat validasi akan tampil di sini.</flux:text>
            @endif
        </div>
    </div>
</section>
