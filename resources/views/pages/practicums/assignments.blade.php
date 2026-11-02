<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-3"><flux:heading size="lg" level="2">Tugas & laporan praktik</flux:heading>@if ($this->canManage)<flux:button wire:click="createAssignment" variant="primary">Tambah tugas</flux:button>@endif</div>
    @if ($showAssignmentForm && $this->canManage)
        <form wire:submit="saveAssignment" class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:heading level="3">{{ $editingAssignmentId ? 'Ubah tugas dan tenggat' : 'Tugas baru' }}</flux:heading>
            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="assignmentForm.title" name="title" label="Judul tugas" required maxlength="255" />
                <flux:input wire:model="assignmentForm.due_at" name="due_at" label="Tenggat (zona waktu kelas)" type="datetime-local" required />
                <flux:input wire:model="assignmentForm.weight" name="weight" label="Bobot terhadap nilai kelas" type="number" min="0.01" max="100" step="0.01" required />
                <div class="self-center"><flux:checkbox wire:model="assignmentForm.allow_late" label="Izinkan pengumpulan melewati tenggat" /></div>
            </div>
            <div class="mt-4"><flux:textarea wire:model="assignmentForm.instructions" name="instructions" label="Instruksi dan hasil yang diharapkan" rows="3" required maxlength="20000" /></div>
            <div class="mt-6 space-y-4">
                <div><flux:heading level="4">Rubrik penilaian</flux:heading><flux:text class="mt-1">Cantumkan hard skill dan soft skill dengan total bobot 100%. Rubrik dan bobot tugas terkunci setelah laporan pertama masuk.</flux:text></div>
                @foreach ($assignmentForm['criteria'] as $index => $criterion)
                    <div wire:key="criterion-form-{{ $index }}" class="grid items-end gap-3 border-b border-zinc-200 pb-4 sm:grid-cols-2 lg:grid-cols-5 dark:border-zinc-700">
                        <flux:input wire:model="assignmentForm.criteria.{{ $index }}.title" :label="'Kriteria '.($index + 1)" required maxlength="255" />
                        <flux:select wire:model="assignmentForm.criteria.{{ $index }}.category" label="Kategori"><option value="hard_skill">Hard skill</option><option value="soft_skill">Soft skill</option></flux:select>
                        <flux:input wire:model="assignmentForm.criteria.{{ $index }}.max_score" label="Skor maksimum" type="number" min="0.01" max="100" step="0.01" required />
                        <flux:input wire:model="assignmentForm.criteria.{{ $index }}.weight" label="Bobot (%)" type="number" min="0.01" max="100" step="0.01" required />
                        <flux:button wire:click="removeCriterion({{ $index }})" variant="ghost">Hapus kriteria</flux:button>
                    </div>
                @endforeach
                <flux:button wire:click="addCriterion" size="sm">Tambah kriteria</flux:button>
            </div>
            <div class="mt-6 flex flex-wrap gap-3"><flux:button type="submit" variant="primary">Simpan tugas & rubrik</flux:button><flux:button wire:click="resetAssignmentForm">Batal</flux:button></div>
        </form>
    @endif
    <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
        @forelse ($this->assignments as $assignment)
            <article wire:key="assignment-{{ $assignment->id }}" class="flex flex-col justify-between gap-4 py-5 first:pt-0 sm:flex-row">
                <div class="space-y-2">
                    <flux:heading level="3">{{ $assignment->title }}</flux:heading>
                    <flux:text>Tenggat {{ $assignment->due_at->setTimezone($this->module->timezone)->format('d M Y, H:i') }} · Bobot {{ $assignment->weight }}</flux:text>
                    @if ($this->canManage)
                        <flux:text>{{ $assignment->pending_count }} laporan menunggu penilaian</flux:text>
                    @elseif ($submission = $assignment->submissions->first())
                        <flux:badge :color="$submission->status === 'graded' ? 'green' : ($submission->status === 'revision_requested' ? 'amber' : 'blue')">{{ $submission->statusLabel() }}{{ $submission->assessment ? ' · '.number_format($submission->assessment->final_score, 2) : '' }}</flux:badge>
                    @else
                        <flux:text>{{ $assignment->due_at->isPast() ? ($assignment->allow_late ? 'Belum dikirim · pengumpulan terlambat diizinkan' : 'Belum dikirim · tenggat terlewat') : 'Belum dikirim' }}</flux:text>
                    @endif
                </div>
                <div class="flex shrink-0 flex-wrap items-start gap-2">
                    <flux:button :href="route('practicums.assignments.show', ['module' => $this->module, 'assignment' => $assignment])" wire:navigate>{{ $this->canManage ? 'Tinjau laporan' : 'Buka tugas' }}</flux:button>
                    @if ($this->canManage)<flux:button wire:click="editAssignment({{ $assignment->id }})" variant="ghost">Ubah</flux:button>@endif
                </div>
            </article>
        @empty
            <flux:text>{{ $this->canManage ? 'Tambahkan tugas dengan instruksi, tenggat, dan rubrik untuk menerima laporan mahasiswa.' : 'Belum ada tugas. Laporan dan revisi akan dapat dikirim melalui bagian ini.' }}</flux:text>
        @endforelse
    </div>
    {{ $this->assignments->links() }}
</div>
