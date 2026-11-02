<?php

use App\Actions\AssessPracticumSubmission;
use App\Actions\SubmitPracticumReport;
use App\Models\PracticumAssignment;
use App\Models\PracticumModule;
use App\Models\PracticumSubmission;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Title('Laporan & Penilaian Praktikum')] class extends Component {
    use WithFileUploads, WithPagination;

    #[Locked]
    public int $moduleId;
    #[Locked]
    public int $assignmentId;
    #[Locked]
    public ?int $selectedSubmissionId = null;
    public ?TemporaryUploadedFile $reportFile = null;
    public string $notes = '';
    public string $reviewStatus = 'submitted';
    public array $reviewForm = ['scores' => [], 'feedback' => ''];
    public string $revisionFeedback = '';

    public function mount(PracticumModule $module, PracticumAssignment $assignment): void
    {
        abort_unless($assignment->practicum_module_id === $module->id, 404);
        Gate::authorize('view', $module);
        $this->moduleId = $module->id;
        $this->assignmentId = $assignment->id;
    }

    #[Computed]
    public function assignment(): PracticumAssignment
    {
        $assignment = PracticumAssignment::query()->forUser(Auth::user())->with(['module.lecturer', 'rubricCriteria'])->where('practicum_module_id', $this->moduleId)->findOrFail($this->assignmentId);
        Gate::authorize('view', $assignment->module);

        return $assignment;
    }

    #[Computed]
    public function canManage(): bool
    {
        return Gate::allows('manage', $this->assignment->module);
    }

    public function submitReport(SubmitPracticumReport $submit): void
    {
        $submit->handle(Auth::user(), $this->assignment->id, $this->reportFile, $this->notes);
        $this->reportFile = null;
        $this->notes = '';
        unset($this->myLatest, $this->history);
        Flux::toast(variant: 'success', text: 'Laporan dikirim untuk dinilai dosen.');
    }

    public function selectSubmission(int $id): void
    {
        Gate::authorize('manage', $this->assignment->module);
        $submission = $this->assignment->submissions()->latestAttempts()->with('assessment')->findOrFail($id);
        Gate::authorize('assess', $submission);
        $this->selectedSubmissionId = $submission->id;
        $existingScores = collect($submission->assessment?->scores ?? [])->pluck('score', 'id');
        $this->reviewForm = ['scores' => $this->assignment->rubricCriteria->mapWithKeys(fn ($criterion): array => [$criterion->id => $existingScores->get($criterion->id, '')])->all(), 'feedback' => $submission->assessment?->feedback ?? ''];
        $this->revisionFeedback = '';
        $this->resetValidation();
        unset($this->selectedSubmission, $this->history);
    }

    public function assess(AssessPracticumSubmission $assess): void
    {
        Gate::authorize('manage', $this->assignment->module);
        $submission = $this->assignment->submissions()->findOrFail($this->selectedSubmissionId);
        $assess->handle(Auth::user(), $submission->id, $this->reviewForm);
        unset($this->submissions, $this->selectedSubmission, $this->history);
        Flux::toast(variant: 'success', text: 'Nilai rubrik dan umpan balik tersimpan.');
    }

    public function requestRevision(AssessPracticumSubmission $assess): void
    {
        Gate::authorize('manage', $this->assignment->module);
        $submission = $this->assignment->submissions()->findOrFail($this->selectedSubmissionId);
        $assess->requestRevision(Auth::user(), $submission->id, $this->revisionFeedback);
        unset($this->submissions, $this->selectedSubmission, $this->history);
        Flux::toast(variant: 'success', text: 'Permintaan revisi dikirim. Mahasiswa dapat mengunggah versi berikutnya.');
    }

    public function updatedReviewStatus(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function submissions(): LengthAwarePaginator
    {
        Gate::authorize('manage', $this->assignment->module);

        return $this->assignment->submissions()->latestAttempts()->with(['student', 'assessment'])
            ->when(in_array($this->reviewStatus, ['submitted', 'revision_requested', 'graded'], true), fn ($query) => $query->where('status', $this->reviewStatus))
            ->latest('submitted_at')->paginate(12);
    }

    #[Computed]
    public function selectedSubmission(): ?PracticumSubmission
    {
        if (! $this->selectedSubmissionId) {
            return null;
        }

        Gate::authorize('manage', $this->assignment->module);

        return $this->assignment->submissions()->with(['student', 'reviewer', 'assessment.assessor'])->findOrFail($this->selectedSubmissionId);
    }

    #[Computed]
    public function myLatest(): ?PracticumSubmission
    {
        Gate::authorize('participate', $this->assignment->module);

        return $this->assignment->submissions()->where('student_id', Auth::id())->latest('attempt')->with(['student', 'reviewer', 'assessment.assessor'])->first();
    }

    #[Computed]
    public function history(): Collection
    {
        $studentId = Auth::id();

        if ($this->canManage) {
            $studentId = $this->selectedSubmission?->student_id;
        }

        return $this->assignment->submissions()->where('student_id', $studentId)->with(['student', 'reviewer', 'assessment.assessor'])->latest('attempt')->get();
    }
}; ?>

<section class="mx-auto flex w-full max-w-6xl flex-col gap-8">
    <header class="space-y-3">
        <flux:button :href="route('practicums.show', ['module' => $this->moduleId, 'tab' => 'assignments'])" wire:navigate variant="ghost" size="sm">Kembali ke tugas kelas</flux:button>
        <flux:heading size="xl" level="1">{{ $this->assignment->title }}</flux:heading>
        <flux:text>{{ $this->assignment->module->title }} · {{ $this->assignment->module->lecturer->name }}</flux:text>
        <div class="flex flex-wrap gap-x-5 gap-y-2 text-sm text-zinc-600 dark:text-zinc-400"><span>Tenggat {{ $this->assignment->due_at->setTimezone($this->assignment->module->timezone)->format('d M Y, H:i') }} ({{ $this->assignment->module->timezone }})</span><span>Bobot tugas {{ $this->assignment->weight }}</span></div>
        <p class="max-w-3xl whitespace-pre-line text-sm leading-7 text-zinc-700 dark:text-zinc-300">{{ $this->assignment->instructions }}</p>
    </header>

    @if ($errors->any())
        <div role="alert" class="rounded-lg bg-red-50 p-4 text-sm text-red-800 dark:bg-red-950 dark:text-red-200"><ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li wire:key="assignment-error-{{ $loop->index }}">{{ $error }}</li>@endforeach</ul></div>
    @endif

    <details class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700" {{ ! $this->canManage ? 'open' : '' }}>
        <summary class="cursor-pointer font-medium">Rubrik & bobot penilaian</summary>
        <div class="mt-4 divide-y divide-zinc-200 dark:divide-zinc-700">
            @foreach ($this->assignment->rubricCriteria as $criterion)
                <div wire:key="rubric-{{ $criterion->id }}" class="flex flex-wrap justify-between gap-2 py-3 text-sm"><span>{{ $criterion->title }} <span class="text-zinc-500 dark:text-zinc-400">· {{ $criterion->category === 'hard_skill' ? 'Hard skill' : 'Soft skill' }}</span></span><span class="tabular-nums">Maks. {{ $criterion->max_score }} · Bobot {{ $criterion->weight }}%</span></div>
            @endforeach
        </div>
        <flux:text class="mt-3">Nilai akhir = jumlah (skor ÷ skor maksimum × bobot) untuk setiap kriteria. Rentang nilai 0–100.</flux:text>
    </details>

    @if ($this->canManage)
        <div class="grid items-start gap-8 lg:grid-cols-[minmax(0,0.85fr)_minmax(0,1.5fr)]">
            <section class="min-w-0 space-y-5">
                <flux:heading size="lg" level="2">Laporan mahasiswa</flux:heading>
                <flux:select wire:model.live="reviewStatus" label="Status laporan"><option value="submitted">Menunggu penilaian</option><option value="revision_requested">Perlu revisi</option><option value="graded">Sudah dinilai</option><option value="">Semua status</option></flux:select>
                <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($this->submissions as $submission)
                        <article wire:key="review-item-{{ $submission->id }}" class="space-y-2 py-4">
                            <flux:heading level="3">{{ $submission->student->name }}</flux:heading>
                            <flux:text>Versi {{ $submission->attempt }} · {{ $submission->submitted_at->setTimezone($this->assignment->module->timezone)->format('d M Y, H:i') }}</flux:text>
                            <div class="flex flex-wrap items-center justify-between gap-2"><flux:badge size="sm" :color="$submission->status === 'graded' ? 'green' : ($submission->status === 'revision_requested' ? 'amber' : 'blue')">{{ $submission->statusLabel() }}</flux:badge><flux:button wire:click="selectSubmission({{ $submission->id }})" :variant="$selectedSubmissionId === $submission->id ? 'primary' : 'outline'" size="sm">Buka laporan</flux:button></div>
                        </article>
                    @empty
                        <flux:text class="py-4">Belum ada laporan dengan status ini. Ubah filter untuk melihat laporan lain.</flux:text>
                    @endforelse
                </div>
                {{ $this->submissions->links() }}
            </section>
            <section class="min-w-0 space-y-6">
                @if ($this->selectedSubmission)
                    <div><flux:heading size="lg" level="2">{{ $this->selectedSubmission->student->name }}</flux:heading><flux:text class="mt-1">Laporan versi {{ $this->selectedSubmission->attempt }}</flux:text></div>
                    <x-practicum-submission-detail :submission="$this->selectedSubmission" :timezone="$this->assignment->module->timezone" />
                    @if (in_array($this->selectedSubmission->status, ['submitted', 'graded'], true))
                        <form wire:submit="assess" class="space-y-5 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                            <flux:heading level="3">{{ $this->selectedSubmission->status === 'graded' ? 'Koreksi nilai rubrik' : 'Penilaian rubrik' }}</flux:heading>
                            @foreach ($this->assignment->rubricCriteria as $criterion)
                                <div wire:key="score-{{ $selectedSubmissionId }}-{{ $criterion->id }}" class="grid items-end gap-3 sm:grid-cols-[1fr_7rem]">
                                    <div><p class="text-sm font-medium">{{ $criterion->title }}</p><flux:text>{{ $criterion->category === 'hard_skill' ? 'Hard skill' : 'Soft skill' }} · Bobot {{ $criterion->weight }}%</flux:text></div>
                                    <flux:input wire:model="reviewForm.scores.{{ $criterion->id }}" :label="'Skor / '.$criterion->max_score" type="number" min="0" :max="$criterion->max_score" step="0.01" required />
                                </div>
                            @endforeach
                            <flux:textarea wire:model="reviewForm.feedback" name="feedback" label="Umpan balik untuk mahasiswa" rows="3" required minlength="3" maxlength="10000" />
                            <flux:button type="submit" variant="primary">Simpan penilaian</flux:button>
                        </form>
                    @endif
                    @if ($this->selectedSubmission->status === 'submitted')
                        <form wire:submit="requestRevision" class="space-y-4">
                            <flux:heading level="3">Minta perbaikan laporan</flux:heading>
                            <flux:textarea wire:model="revisionFeedback" name="revision_feedback" label="Bagian yang harus diperbaiki" rows="3" required minlength="5" maxlength="10000" />
                            <flux:button type="submit">Kirim permintaan revisi</flux:button>
                        </form>
                    @endif
                    @if ($this->history->count() > 1)
                        <details class="border-t border-zinc-200 pt-5 dark:border-zinc-700"><summary class="cursor-pointer font-medium">Riwayat versi sebelumnya</summary><div class="mt-4 space-y-6">@foreach ($this->history->where('id', '!=', $selectedSubmissionId) as $previous)<div wire:key="previous-{{ $previous->id }}"><x-practicum-submission-detail :submission="$previous" :timezone="$this->assignment->module->timezone" /></div>@endforeach</div></details>
                    @endif
                @else
                    <div class="rounded-xl border border-dashed border-zinc-300 p-8 dark:border-zinc-700"><flux:heading level="2">Pilih laporan untuk dinilai</flux:heading><flux:text class="mt-2">Unduh karya mahasiswa, isi skor setiap kriteria, lalu simpan umpan balik atau minta revisi.</flux:text></div>
                @endif
            </section>
        </div>
    @else
        @if ($this->myLatest)
            <section class="space-y-4"><flux:heading size="lg" level="2">Laporan terbaru</flux:heading><x-practicum-submission-detail :submission="$this->myLatest" :timezone="$this->assignment->module->timezone" /></section>
        @endif
        @if (! $this->myLatest || $this->myLatest->status === 'revision_requested')
            @if ($this->myLatest || $this->assignment->allow_late || ! $this->assignment->due_at->isPast())
                <form wire:submit="submitReport" class="max-w-3xl space-y-5 rounded-xl border border-zinc-200 p-5 sm:p-6 dark:border-zinc-700">
                    <div><flux:heading size="lg" level="2">{{ $this->myLatest ? 'Kirim revisi laporan' : 'Kumpulkan laporan' }}</flux:heading><flux:text class="mt-1">{{ $this->myLatest ? 'Versi sebelumnya tetap tersimpan. Permintaan dosen membuka pengiriman revisi meskipun tenggat awal telah lewat.' : 'Pastikan berkas sudah lengkap. Setelah terkirim, Anda dapat memperbaruinya jika dosen meminta revisi.' }}</flux:text></div>
                    <flux:input wire:model="reportFile" name="file" label="Berkas laporan" type="file" accept=".pdf,.docx,.txt,.jpg,.jpeg,.png" required />
                    <flux:text>PDF, DOCX, TXT, JPG, atau PNG; maksimal 12 MB.</flux:text>
                    <p wire:loading wire:target="reportFile" role="status" class="text-sm">Mengunggah laporan…</p>
                    <flux:textarea wire:model="notes" name="notes" label="Catatan untuk dosen (opsional)" rows="3" maxlength="10000" />
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="reportFile,submitReport">{{ $this->myLatest ? 'Kirim versi revisi' : 'Kirim laporan' }}</flux:button>
                </form>
            @else
                <flux:text>Tenggat pengumpulan sudah lewat. Hubungi dosen jika membutuhkan perpanjangan waktu.</flux:text>
            @endif
        @endif
        @if ($this->history->count() > 1)
            <section class="space-y-5"><flux:heading size="lg" level="2">Riwayat revisi</flux:heading>@foreach ($this->history->skip(1) as $previous)<div wire:key="my-previous-{{ $previous->id }}" class="border-t border-zinc-200 pt-5 dark:border-zinc-700"><x-practicum-submission-detail :submission="$previous" :timezone="$this->assignment->module->timezone" /></div>@endforeach</section>
        @endif
    @endif
</section>
