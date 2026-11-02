<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <flux:heading size="lg" level="2">Jadwal laboratorium</flux:heading>
        @if ($this->canManage)<flux:button wire:click="createSession" variant="primary">Tambah sesi</flux:button>@endif
    </div>
    @if ($showSessionForm && $this->canManage)
        <form wire:submit="saveSession" class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:heading level="3">{{ $editingSessionId ? 'Ubah sesi' : 'Sesi baru' }}</flux:heading>
            <flux:text class="mt-1">Jadwal menggunakan zona waktu {{ $this->module->timezone }}. Sesi dengan presensi tersimpan tidak dapat diubah.</flux:text>
            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="sessionForm.title" name="title" label="Topik sesi" required maxlength="255" />
                <flux:input wire:model="sessionForm.room" name="room" label="Laboratorium / ruangan" required maxlength="255" />
                <flux:input wire:model="sessionForm.starts_at" name="starts_at" label="Mulai" type="datetime-local" required />
                <flux:input wire:model="sessionForm.ends_at" name="ends_at" label="Selesai" type="datetime-local" required />
                <flux:checkbox wire:model="sessionForm.is_exam" label="Sesi ujian praktik" />
                @if ($editingSessionId)<flux:checkbox wire:model="sessionForm.cancelled" label="Batalkan sesi ini" />@endif
            </div>
            <div class="mt-5 flex flex-wrap gap-3"><flux:button type="submit" variant="primary">Simpan jadwal</flux:button><flux:button wire:click="resetSessionForm">Batal mengubah</flux:button></div>
        </form>
    @endif
    <div class="divide-y divide-zinc-200 dark:divide-zinc-700" wire:loading.class="opacity-60">
        @forelse ($this->sessions as $session)
            <article wire:key="session-{{ $session->id }}" class="flex flex-col justify-between gap-4 py-5 first:pt-0 sm:flex-row">
                <div class="space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <flux:heading level="3">{{ $session->title }}</flux:heading>
                        @if ($session->is_exam)<flux:badge size="sm" color="amber">Ujian praktik</flux:badge>@endif
                        @if ($session->cancelled)<flux:badge size="sm" color="red">Dibatalkan</flux:badge>@elseif ($session->isOpen())<flux:badge size="sm" color="green">Sedang berlangsung</flux:badge>@endif
                    </div>
                    <flux:text>{{ $session->starts_at->setTimezone($this->module->timezone)->format('d M Y, H:i') }} – {{ $session->ends_at->setTimezone($this->module->timezone)->format('H:i') }} · {{ $session->room }}</flux:text>
                    @if ($this->canManage)<flux:text>{{ $session->attendances_count }} presensi tercatat</flux:text>@endif
                </div>
                <div class="flex shrink-0 items-start gap-3">
                    @if ($this->canManage)
                        @if ($session->attendances_count === 0)<flux:button wire:click="editSession({{ $session->id }})" size="sm">Ubah jadwal</flux:button>@endif
                    @elseif ($attendance = $session->attendances->first())
                        <flux:badge :color="in_array($attendance->status, ['present', 'late'], true) ? 'green' : 'zinc'">{{ $attendance->statusLabel() }}</flux:badge>
                    @elseif ($session->isOpen())
                        <flux:button wire:click="checkIn({{ $session->id }})" variant="primary">Catat kehadiran</flux:button>
                    @else
                        <flux:text>{{ $session->ends_at->isPast() ? 'Tidak ada presensi' : 'Presensi dibuka saat sesi dimulai' }}</flux:text>
                    @endif
                </div>
            </article>
        @empty
            <flux:text>{{ $this->canManage ? 'Tambahkan sesi pertama beserta lokasi dan waktunya. Mahasiswa dapat mencatat kehadiran selama sesi berlangsung.' : 'Dosen belum menerbitkan jadwal sesi. Materi dan tugas tetap dapat Anda buka melalui tab di atas.' }}</flux:text>
        @endforelse
    </div>
    {{ $this->sessions->links() }}
    @if ($this->canManage)<flux:text>Untuk mencatat atau mengoreksi kehadiran mahasiswa, buka bagian Peserta & capaian.</flux:text>@endif
</div>
