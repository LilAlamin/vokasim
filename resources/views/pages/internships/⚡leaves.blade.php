<?php

use App\Actions\ReviewInternshipLeave;
use App\Actions\SubmitInternshipLeave;
use App\Models\InternshipLeaveRequest;
use App\Models\InternshipPlacement;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Title('Izin & Sakit Magang')] class extends Component
{
    use WithFileUploads, WithPagination;

    public string $placementId = '';

    public string $status = '';

    public array $form = [];

    public $evidence = null;

    #[Locked]
    public ?int $selectedId = null;

    public string $feedback = '';

    public function mount(): void
    {
        $this->form = ['kind' => 'leave', 'starts_on' => today()->toDateString(), 'ends_on' => today()->toDateString(), 'reason' => ''];
        $this->placementId = (string) (InternshipPlacement::query()->forUser(Auth::user())->latest('starts_on')->value('id') ?? '');
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function submit(SubmitInternshipLeave $submit): void
    {
        Validator::make(['placement_id' => $this->placementId], ['placement_id' => ['required', 'integer']])->validate();
        $submit->handle(Auth::user(), (int) $this->placementId, $this->form, $this->evidence);
        $this->form['reason'] = '';
        $this->reset('evidence');
        $this->resetValidation();
        $this->resetPage();
        unset($this->entries);
        Flux::toast(variant: 'success', text: 'Pengajuan dikirim kepada mentor penempatan Anda.');
    }

    public function selectLeave(int $id): void
    {
        $leave = InternshipLeaveRequest::query()->forUser(Auth::user())->with('placement')->findOrFail($id);
        Gate::authorize('review', $leave);
        $this->selectedId = $id;
        $this->feedback = '';
        $this->resetValidation();
        unset($this->selected);
    }

    public function review(string $decision, ReviewInternshipLeave $review): void
    {
        abort_unless($this->selectedId, 404);
        $this->resetValidation();
        $review->handle(Auth::user(), $this->selectedId, $decision, $this->feedback);
        $this->selectedId = null;
        $this->feedback = '';
        unset($this->entries, $this->selected);
        Flux::toast(variant: 'success', text: 'Keputusan pengajuan tersimpan.');
    }

    public function cancelReview(): void
    {
        $this->selectedId = null;
        $this->feedback = '';
        $this->resetValidation();
        unset($this->selected);
    }

    #[Computed]
    public function placements(): \Illuminate\Database\Eloquent\Collection
    {
        return InternshipPlacement::query()->forUser(Auth::user())->with('student')->orderByDesc('starts_on')->get();
    }

    #[Computed]
    public function entries(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        Validator::make(['status' => $this->status], ['status' => ['nullable', \Illuminate\Validation\Rule::in(['pending', 'approved', 'rejected'])]])->validate();

        return InternshipLeaveRequest::query()->forUser(Auth::user())->with(['placement.student', 'reviewer'])
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))->latest('id')->paginate(10);
    }

    #[Computed]
    public function selected(): ?InternshipLeaveRequest
    {
        return $this->selectedId ? InternshipLeaveRequest::query()->forUser(Auth::user())->with('placement.student')->findOrFail($this->selectedId) : null;
    }
}; ?>

<section class="mx-auto flex w-full max-w-6xl flex-col gap-7">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">Izin & Sakit Magang</flux:heading>
            <flux:text class="mt-2">Ajukan ketidakhadiran sehari penuh dan pantau keputusan mentor.</flux:text>
        </div>
        <flux:button :href="route('industry.index')" wire:navigate>Rekap magang</flux:button>
    </header>

    @if ($errors->any())
        <div role="alert" class="rounded-lg bg-red-50 p-4 text-sm text-red-800 dark:bg-red-950 dark:text-red-200"><ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li wire:key="leave-error-{{ $loop->index }}">{{ $error }}</li>@endforeach</ul></div>
    @endif

    @if (auth()->user()->isMahasiswa())
        <form wire:submit="submit" class="rounded-xl border border-zinc-200 p-5 sm:p-6 dark:border-zinc-700">
            <flux:heading size="lg" level="2">Ajukan izin atau sakit</flux:heading>
            <flux:text class="mt-2">Tanggal harus berada dalam periode magang. Bukti sakit wajib dilampirkan dan hanya dapat diunduh oleh Anda, mentor penempatan, dan administrator.</flux:text>
            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="placementId" name="placement_id" label="Penempatan magang" required>
                    <option value="">Pilih penempatan</option>
                    @foreach ($this->placements as $placement)<option wire:key="leave-placement-{{ $placement->id }}" value="{{ $placement->id }}">{{ $placement->company_name }} · {{ $placement->starts_on->format('d M Y') }}</option>@endforeach
                </flux:select>
                <flux:select wire:model.live="form.kind" name="kind" label="Jenis pengajuan" required><option value="leave">Izin</option><option value="sick">Sakit</option></flux:select>
                <flux:input wire:model="form.starts_on" name="starts_on" label="Tanggal mulai" type="date" required />
                <flux:input wire:model="form.ends_on" name="ends_on" label="Tanggal selesai" type="date" required />
            </div>
            <div class="mt-4 flex flex-col gap-4">
                <flux:textarea wire:model="form.reason" name="reason" label="Alasan pengajuan" rows="3" maxlength="2000" required />
                <flux:input type="file" wire:model="evidence" label="Surat / bukti pendukung" description="PDF, JPG, atau PNG; maksimal 5 MB. Wajib untuk sakit." accept=".pdf,.jpg,.jpeg,.png" />
                <span wire:loading wire:target="evidence" role="status" class="text-sm text-zinc-600 dark:text-zinc-300">Mengunggah bukti…</span>
                <div><flux:button type="submit" variant="primary" :disabled="$this->placements->isEmpty()" wire:loading.attr="disabled" wire:target="submit,evidence">Kirim pengajuan</flux:button></div>
                @if ($this->placements->isEmpty())<flux:text>Penempatan belum tersedia. Administrator perlu menghubungkan akun Anda dengan lokasi magang.</flux:text>@endif
            </div>
        </form>
    @endif

    @if ($this->selected && $this->selected->status === 'pending')
        <form wire:submit="review('approved')" class="flex flex-col gap-4 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:heading size="lg" level="2">Tinjau {{ $this->selected->placement->student->name }}</flux:heading>
            <flux:text>{{ $this->selected->kind === 'sick' ? 'Sakit' : 'Izin' }} · {{ $this->selected->starts_on->format('d M Y') }} – {{ $this->selected->ends_on->format('d M Y') }}</flux:text>
            <p class="whitespace-pre-wrap break-words text-sm">{{ $this->selected->reason }}</p>
            @if ($this->selected->evidence_path)<div><flux:button :href="route('internships.leaves.evidence', $this->selected)" size="sm">Unduh bukti privat</flux:button></div>@endif
            <flux:textarea wire:model="feedback" label="Catatan keputusan" description="Wajib diisi saat menolak pengajuan." rows="3" maxlength="2000" />
            <div class="flex flex-wrap gap-3">
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">Setujui pengajuan</flux:button>
                <flux:button type="button" wire:click="review('rejected')" wire:loading.attr="disabled">Tolak pengajuan</flux:button>
                <flux:button type="button" wire:click="cancelReview">Batal</flux:button>
            </div>
        </form>
    @endif

    <div class="flex flex-col gap-4">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <flux:heading size="lg" level="2">Riwayat pengajuan</flux:heading>
            <flux:select wire:model.live="status" label="Filter status" class="w-full sm:max-w-60"><option value="">Semua status</option><option value="pending">Menunggu mentor</option><option value="approved">Disetujui</option><option value="rejected">Ditolak</option></flux:select>
        </div>
        @forelse ($this->entries as $leave)
            <article wire:key="leave-{{ $leave->id }}" class="flex flex-col gap-3 border-b border-zinc-200 pb-5 dark:border-zinc-700">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <flux:heading level="3">{{ $leave->placement->student->name }} · {{ $leave->kind === 'sick' ? 'Sakit' : 'Izin' }}</flux:heading>
                    <flux:badge :color="match ($leave->status) { 'approved' => 'green', 'rejected' => 'red', default => 'amber' }">{{ $leave->statusLabel() }}</flux:badge>
                </div>
                <flux:text>{{ $leave->placement->company_name }} · {{ $leave->starts_on->format('d M Y') }} – {{ $leave->ends_on->format('d M Y') }}</flux:text>
                <p class="whitespace-pre-wrap break-words text-sm">{{ $leave->reason }}</p>
                @if ($leave->feedback)<p class="whitespace-pre-wrap break-words text-sm text-zinc-600 dark:text-zinc-300">Catatan mentor: {{ $leave->feedback }}</p>@endif
                @if ($leave->reviewed_at)<flux:text>{{ $leave->reviewer?->name }} · {{ $leave->reviewed_at->timezone($leave->placement->timezone)->format('d M Y H:i') }}</flux:text>@endif
                <div class="flex flex-wrap gap-3">
                    @if ($leave->evidence_path)
                        @can('viewEvidence', $leave)
                            <flux:button :href="route('internships.leaves.evidence', $leave)" size="sm">Unduh bukti</flux:button>
                        @endcan
                    @endif
                    @if ($leave->status === 'pending')
                        @can('review', $leave)
                            <flux:button wire:click="selectLeave({{ $leave->id }})" size="sm">Tinjau pengajuan</flux:button>
                        @endcan
                    @endif
                </div>
            </article>
        @empty
            <div class="border-y border-dashed border-zinc-300 py-8 dark:border-zinc-700"><flux:heading level="3">Belum ada pengajuan pada status ini</flux:heading><flux:text class="mt-2">Pengajuan dari mahasiswa yang berada dalam penempatan Anda akan muncul di sini.</flux:text></div>
        @endforelse
        {{ $this->entries->links() }}
    </div>
</section>
