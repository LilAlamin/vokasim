<?php

use App\Actions\SaveInternshipProject;
use App\Enums\LogbookStatus;
use App\Models\InternshipPlacement;
use App\Models\InternshipProject;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Rekap & Proyek Magang')] class extends Component
{
    use WithPagination;

    public string $placementId = '';

    public array $form = [];

    #[Locked]
    public ?int $editingId = null;

    public function mount(): void
    {
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->form = ['title' => '', 'description' => '', 'starts_on' => today()->toDateString(), 'due_on' => today()->addWeek()->toDateString(), 'target_minutes' => 480, 'completed_minutes' => 0, 'feedback' => ''];
        $this->resetValidation();
    }

    public function editProject(int $id): void
    {
        $project = InternshipProject::query()->forUser(Auth::user())->with('placement')->findOrFail($id);
        Gate::authorize('update', $project);
        $this->editingId = $project->id;
        $this->placementId = (string) $project->internship_placement_id;
        $this->form = $project->only(['title', 'description', 'target_minutes', 'completed_minutes', 'feedback']);
        $this->form['starts_on'] = $project->starts_on->toDateString();
        $this->form['due_on'] = $project->due_on->toDateString();
        $this->resetValidation();
    }

    public function saveProject(SaveInternshipProject $save): void
    {
        Validator::make(['placement_id' => $this->placementId], ['placement_id' => ['required', 'integer']])->validate();
        $save->handle(Auth::user(), (int) $this->placementId, $this->form, $this->editingId);
        $this->resetForm();
        unset($this->projects);
        Flux::toast(variant: 'success', text: 'Target dan capaian proyek tersimpan.');
    }

    #[Computed]
    public function placements(): \Illuminate\Database\Eloquent\Collection
    {
        return InternshipPlacement::query()->forUser(Auth::user())->with('student')->orderByDesc('starts_on')->get();
    }

    #[Computed]
    public function recaps(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return InternshipPlacement::query()->forUser(Auth::user())->with(['student', 'unit'])
            ->withCount([
                'attendances',
                'attendances as completed_attendances_count' => fn ($query) => $query->whereNotNull('checked_out_at'),
                'leaveRequests as approved_leaves_count' => fn ($query) => $query->where('status', 'approved'),
                'logbooks as pending_logbooks_count' => fn ($query) => $query->whereIn('status', [LogbookStatus::PendingMentor, LogbookStatus::MentorApproved]),
                'projects',
            ])
            ->withSum(['logbooks as mentor_minutes' => fn ($query) => $query->whereIn('status', [LogbookStatus::MentorApproved, LogbookStatus::Verified])], 'effective_minutes')
            ->withSum(['logbooks as verified_minutes' => fn ($query) => $query->where('status', LogbookStatus::Verified)], 'effective_minutes')
            ->orderByDesc('starts_on')->orderByDesc('id')->paginate(10, pageName: 'recaps');
    }

    #[Computed]
    public function projects(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return InternshipProject::query()->forUser(Auth::user())->with(['placement.student', 'mentor'])
            ->orderBy('due_on')->orderBy('id')->paginate(10, pageName: 'projects');
    }
}; ?>

<section class="mx-auto flex w-full max-w-6xl flex-col gap-7">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div><flux:heading size="xl" level="1">Rekap & Proyek Magang</flux:heading><flux:text class="mt-2">Pantau jam kerja terverifikasi, kelengkapan presensi, dan kontribusi proyek pada penempatan Anda.</flux:text></div>
        <div class="flex flex-wrap gap-3"><flux:button :href="route('internships.leaves')" wire:navigate>Izin & sakit</flux:button><flux:button :href="route('internships.evaluations')" wire:navigate>Evaluasi kinerja</flux:button></div>
    </header>
    @if ($errors->any())<div role="alert" class="rounded-lg bg-red-50 p-4 text-sm text-red-800 dark:bg-red-950 dark:text-red-200"><ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li wire:key="project-error-{{ $loop->index }}">{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="flex flex-col gap-4">
        <flux:heading size="lg" level="2">Kehadiran & capaian kerja</flux:heading>
        <flux:text>Rekap mencakup seluruh periode penempatan. Kelengkapan presensi menunjukkan hari dengan presensi pulang dari hari yang mencatat presensi datang; ini bukan persentase kehadiran terhadap jadwal kerja.</flux:text>
        <div class="overflow-x-auto">
            <table class="w-full min-w-160 text-left text-sm">
                <caption class="sr-only">Rekap jam kerja, kelengkapan presensi, dan izin mahasiswa magang</caption>
                <thead class="border-b border-zinc-200 text-zinc-600 dark:border-zinc-700 dark:text-zinc-300"><tr><th scope="col" class="py-3 pr-5 font-medium">Mahasiswa / penempatan</th><th scope="col" class="py-3 pr-5 text-right font-medium">Jam disetujui mentor</th><th scope="col" class="py-3 pr-5 text-right font-medium">Terverifikasi / target</th><th scope="col" class="py-3 pr-5 text-right font-medium">Presensi lengkap</th><th scope="col" class="py-3 text-right font-medium">Izin disetujui</th></tr></thead>
                <tbody>
                    @forelse ($this->recaps as $placement)
                        <tr wire:key="industry-recap-{{ $placement->id }}" class="border-b border-zinc-200 align-top dark:border-zinc-700">
                            <th scope="row" class="py-4 pr-5 font-medium">{{ $placement->student->name }}<span class="mt-1 block font-normal text-zinc-600 dark:text-zinc-300">{{ $placement->company_name }}{{ $placement->unit ? ' · '.$placement->unit->name : '' }}</span><span class="mt-1 block text-xs font-normal text-zinc-600 dark:text-zinc-300">{{ $placement->pending_logbooks_count }} logbook menunggu · {{ $placement->projects_count }} proyek</span></th>
                            <td class="py-4 pr-5 text-right tabular-nums">{{ number_format(($placement->mentor_minutes ?? 0) / 60, 1) }}</td>
                            <td class="py-4 pr-5 text-right tabular-nums">{{ number_format(($placement->verified_minutes ?? 0) / 60, 1) }} / {{ $placement->target_hours }}</td>
                            <td class="py-4 pr-5 text-right tabular-nums">
                                {{ $placement->completed_attendances_count }} / {{ $placement->attendances_count }} hari
                                @if ($placement->attendances_count > $placement->completed_attendances_count)
                                    <span class="mt-1 block text-xs text-amber-800 dark:text-amber-300">{{ $placement->attendances_count - $placement->completed_attendances_count }} belum pulang</span>
                                @endif
                            </td>
                            <td class="py-4 text-right tabular-nums">{{ $placement->approved_leaves_count }} pengajuan</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-8 text-zinc-600 dark:text-zinc-300">Belum ada penempatan dalam lingkup akun Anda. Administrator dapat menghubungkan mahasiswa dan pembimbing melalui penempatan magang.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $this->recaps->links() }}
    </section>

    @if (auth()->user()->isMentorDudi())
        <form wire:submit="saveProject" class="flex flex-col gap-5 rounded-xl border border-zinc-200 p-5 sm:p-6 dark:border-zinc-700">
            <div><flux:heading size="lg" level="2">{{ $editingId ? 'Perbarui capaian proyek' : 'Tetapkan proyek / target kerja' }}</flux:heading><flux:text class="mt-2">Gunakan rentang satu hari untuk target harian. Capaian proyek dicatat oleh mentor; jam magang tetap mengikuti validasi logbook.</flux:text></div>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="placementId" name="placement_id" label="Mahasiswa / penempatan" :disabled="$editingId !== null" required><option value="">Pilih mahasiswa</option>@foreach ($this->placements as $placement)<option wire:key="project-placement-{{ $placement->id }}" value="{{ $placement->id }}">{{ $placement->student->name }} · {{ $placement->company_name }}</option>@endforeach</flux:select>
                <flux:input wire:model="form.title" name="title" label="Nama proyek / target kerja" maxlength="255" required />
            </div>
            <flux:textarea wire:model="form.description" name="description" label="Lingkup tugas & hasil yang diharapkan" rows="3" maxlength="3000" required />
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <flux:input wire:model="form.starts_on" name="starts_on" label="Mulai dikerjakan" type="date" required />
                <flux:input wire:model="form.due_on" name="due_on" label="Tenggat" type="date" required />
                <flux:input wire:model="form.target_minutes" name="target_minutes" label="Target kerja (menit)" type="number" min="1" max="300000" required />
                <flux:input wire:model="form.completed_minutes" name="completed_minutes" label="Capaian ditinjau (menit)" type="number" min="0" max="300000" required />
            </div>
            <flux:textarea wire:model="form.feedback" name="feedback" label="Catatan capaian proyek" rows="2" maxlength="2000" />
            <div class="flex flex-wrap gap-3"><flux:button type="submit" variant="primary" :disabled="$this->placements->isEmpty()" wire:loading.attr="disabled">Simpan proyek</flux:button><flux:button type="button" wire:click="resetForm">Kosongkan formulir</flux:button></div>
        </form>
    @endif

    <section class="flex flex-col gap-5">
        <flux:heading size="lg" level="2">Keikutsertaan proyek lapangan</flux:heading>
        @forelse ($this->projects as $project)
            <article wire:key="industry-project-{{ $project->id }}" class="flex flex-col gap-3 border-b border-zinc-200 pb-5 dark:border-zinc-700">
                <div class="flex flex-wrap items-start justify-between gap-3"><div><flux:heading level="3">{{ $project->title }}</flux:heading><flux:text class="mt-1">{{ $project->placement->student->name }} · {{ $project->placement->company_name }}</flux:text></div><flux:badge :color="$project->completionPercentage() === 100 ? 'green' : 'blue'">{{ $project->completionPercentage() }}% target</flux:badge></div>
                <p class="whitespace-pre-wrap break-words text-sm">{{ $project->description }}</p>
                <flux:text>{{ $project->starts_on->format('d M Y') }} – {{ $project->due_on->format('d M Y') }} · {{ number_format($project->completed_minutes / 60, 1) }} / {{ number_format($project->target_minutes / 60, 1) }} jam proyek ditinjau mentor</flux:text>
                @if ($project->feedback)<p class="whitespace-pre-wrap break-words text-sm text-zinc-600 dark:text-zinc-300">{{ $project->feedback }}</p>@endif
                <div class="flex flex-wrap items-center justify-between gap-3"><flux:text>Mentor: {{ $project->mentor->name }}</flux:text>@can('update', $project)<flux:button wire:click="editProject({{ $project->id }})" size="sm">Perbarui capaian</flux:button>@endcan</div>
            </article>
        @empty
            <div class="border-y border-dashed border-zinc-300 py-8 dark:border-zinc-700"><flux:heading level="3">Belum ada proyek yang ditetapkan</flux:heading><flux:text class="mt-2">Mentor dapat menambahkan target kerja dan meninjau kontribusi mahasiswa dari halaman ini.</flux:text></div>
        @endforelse
        {{ $this->projects->links() }}
    </section>
</section>
