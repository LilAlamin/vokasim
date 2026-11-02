<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="lg" level="2">Panduan keselamatan & materi</flux:heading>
        <flux:text class="mt-1">Pelajari panduan K3 dan SOP sebelum menggunakan peralatan laboratorium.</flux:text>
    </div>
    @if ($this->canManage)
        <form wire:submit="uploadMaterial" class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="materialForm.title" name="title" label="Judul materi" required maxlength="255" />
                <flux:select wire:model="materialForm.kind" name="kind" label="Jenis dokumen"><option value="k3">Panduan K3</option><option value="sop">SOP laboratorium</option><option value="material">Materi praktik</option></flux:select>
            </div>
            <div class="mt-4"><flux:input wire:model="materialFile" name="file" type="file" label="Berkas materi" accept=".pdf,.docx,.pptx,.xlsx,.txt,.jpg,.jpeg,.png" required /></div>
            <flux:text class="mt-2">PDF, Office, TXT, JPG, atau PNG; maksimal 12 MB. Berkas hanya tersedia untuk peserta dan pengelola kelas.</flux:text>
            <p wire:loading wire:target="materialFile" role="status" class="mt-2 text-sm">Mengunggah berkas…</p>
            <flux:button class="mt-4" type="submit" variant="primary" wire:loading.attr="disabled" wire:target="materialFile,uploadMaterial">Unggah materi</flux:button>
        </form>
    @endif
    <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
        @forelse ($this->materials as $material)
            <article wire:key="material-{{ $material->id }}" class="flex flex-col justify-between gap-3 py-4 sm:flex-row">
                <div class="min-w-0 space-y-1"><flux:heading level="3" class="break-words">{{ $material->title }}</flux:heading><flux:text>{{ $material->kindLabel() }} · {{ number_format($material->size / 1024, 0) }} KB · {{ $material->created_at->format('d M Y') }}</flux:text></div>
                <div class="flex shrink-0 flex-wrap items-start gap-2">
                    @if ($material->canPreview())<flux:button wire:click="previewMaterial({{ $material->id }})" size="sm">Baca panduan</flux:button>@endif
                    <flux:button :href="route('practicums.materials.download', $material)" size="sm">Unduh</flux:button>
                    @if ($this->canManage)<flux:button wire:click="removeMaterial({{ $material->id }})" wire:confirm="Hapus materi ini dari kelas?" variant="ghost" size="sm">Hapus</flux:button>@endif
                </div>
            </article>
        @empty
            <flux:text>{{ $this->canManage ? 'Unggah panduan K3, SOP, atau bahan praktik pertama untuk kelas ini.' : 'Materi belum tersedia. Dosen akan menambahkan panduan dan bahan praktik di sini.' }}</flux:text>
        @endforelse
    </div>
    @if ($this->selectedMaterial && $this->selectedMaterial->canPreview())
        <section class="space-y-3" aria-label="Pembaca materi">
            <div class="flex flex-wrap items-center justify-between gap-3"><flux:heading level="3">{{ $this->selectedMaterial->title }}</flux:heading><flux:button :href="route('practicums.materials.show', $this->selectedMaterial)" target="_blank" rel="noopener" size="sm">Buka di tab baru</flux:button></div>
            <iframe src="{{ route('practicums.materials.show', $this->selectedMaterial) }}" title="{{ $this->selectedMaterial->title }}" class="h-[32rem] w-full rounded-xl border border-zinc-200 bg-white dark:border-zinc-700"></iframe>
            <flux:text>Jika pratinjau tidak didukung perangkat, gunakan tombol Unduh.</flux:text>
        </section>
    @endif
</div>
