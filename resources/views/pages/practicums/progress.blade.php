<div class="flex flex-col gap-6">
    <div><flux:heading size="lg" level="2">{{ $this->canManage ? 'Peserta & capaian kelas' : 'Capaian saya' }}</flux:heading><flux:text class="mt-1">Nilai rata-rata mengikuti bobot tugas yang sudah dinilai. Persentase capaian menunjukkan tugas yang selesai dinilai; kehadiran dihitung dari sesi yang sudah dimulai.</flux:text></div>
    @if ($this->canManage)
        <form wire:submit="enrollStudent" class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:heading level="3">Tambah peserta kelas</flux:heading>
            <div class="mt-4 grid items-end gap-4 sm:grid-cols-2">
                <flux:input wire:model.live.debounce.350ms="studentSearch" label="Cari mahasiswa" placeholder="Nama atau NIM" type="search" />
                <flux:select wire:model="studentId" label="Mahasiswa" required><option value="">Pilih mahasiswa</option>@foreach ($this->availableStudents as $student)<option value="{{ $student->id }}" wire:key="available-student-{{ $student->id }}">{{ $student->name }} · {{ $student->identifier_number ?: $student->study_program }}</option>@endforeach</flux:select>
            </div>
            <flux:text class="mt-2">Menampilkan hingga 50 hasil. Gunakan pencarian jika mahasiswa belum terlihat.</flux:text>
            <flux:button type="submit" variant="primary" class="mt-4">Tambahkan peserta</flux:button>
        </form>
        <div class="max-w-xl"><flux:select wire:model.live="attendanceSessionId" label="Catat atau koreksi presensi sesi"><option value="">Pilih sesi yang sudah dimulai</option>@foreach ($this->attendanceSessions as $session)<option value="{{ $session->id }}" wire:key="attendance-session-{{ $session->id }}">{{ $session->starts_at->setTimezone($this->module->timezone)->format('d M, H:i') }} · {{ $session->title }}</option>@endforeach</flux:select></div>
        <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
            @forelse ($this->enrollments as $enrollment)
                <article wire:key="enrollment-{{ $enrollment->id }}" class="space-y-5 py-6">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex flex-wrap items-center gap-2"><flux:heading level="3">{{ $enrollment->student->name }}</flux:heading><flux:badge size="sm" :color="$enrollment->status === 'active' ? 'green' : 'zinc'">{{ $enrollment->status === 'active' ? 'Aktif' : 'Dinonaktifkan' }}</flux:badge></div>
                        <flux:button wire:click="changeEnrollment({{ $enrollment->id }}, '{{ $enrollment->status === 'active' ? 'withdrawn' : 'active' }}')" :wire:confirm="$enrollment->status === 'active' ? 'Nonaktifkan akses peserta ini? Riwayat laporan dan nilai tetap tersimpan.' : 'Aktifkan kembali peserta ini?'" size="sm" variant="ghost">{{ $enrollment->status === 'active' ? 'Nonaktifkan peserta' : 'Aktifkan kembali' }}</flux:button>
                    </div>
                    <x-practicum-progress :progress="$this->progress[$enrollment->student_id]" />
                    @if ($attendanceSessionId !== '' && $enrollment->status === 'active')
                        <form wire:submit="saveAttendance({{ $enrollment->student_id }})" class="grid items-end gap-3 sm:grid-cols-3">
                            <flux:select wire:model="attendanceStates.{{ $enrollment->student_id }}" :label="'Presensi '.$enrollment->student->name" required><option value="">Pilih status</option><option value="present">Hadir</option><option value="late">Terlambat</option><option value="excused">Izin / sakit</option><option value="absent">Tidak hadir</option></flux:select>
                            <flux:input wire:model="attendanceNotes.{{ $enrollment->student_id }}" label="Catatan presensi (opsional)" maxlength="2000" />
                            <flux:button type="submit">Simpan presensi</flux:button>
                        </form>
                    @endif
                </article>
            @empty
                <flux:text>Belum ada peserta. Tambahkan mahasiswa agar mereka dapat mengakses kelas yang sudah diterbitkan.</flux:text>
            @endforelse
        </div>
        {{ $this->enrollments->links() }}
    @else
        <x-practicum-progress :progress="$this->progress[auth()->id()]" class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700" />
        <flux:text>Buka Tugas & laporan untuk membaca rincian rubrik, nilai per kriteria, dan umpan balik dosen.</flux:text>
    @endif
</div>
