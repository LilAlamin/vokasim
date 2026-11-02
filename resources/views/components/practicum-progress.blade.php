@props(['progress'])
<dl {{ $attributes->class(['grid grid-cols-2 gap-x-6 gap-y-3 text-sm sm:grid-cols-3']) }}>
    <div><dt class="text-zinc-500 dark:text-zinc-400">Kehadiran</dt><dd class="mt-1 font-medium tabular-nums">{{ $progress['attended_count'] }}/{{ $progress['session_count'] }} sesi ({{ $progress['attendance_percentage'] }}%)</dd></div>
    <div><dt class="text-zinc-500 dark:text-zinc-400">Tugas dinilai</dt><dd class="mt-1 font-medium tabular-nums">{{ $progress['graded_count'] }}/{{ $progress['assignment_count'] }} ({{ $progress['completion_percentage'] }}%)</dd></div>
    <div><dt class="text-zinc-500 dark:text-zinc-400">Rata-rata berbobot</dt><dd class="mt-1 font-medium tabular-nums">{{ $progress['average_grade'] !== null ? number_format($progress['average_grade'], 2) : 'Belum ada nilai' }}</dd></div>
    <div><dt class="text-zinc-500 dark:text-zinc-400">Hard skill</dt><dd class="mt-1 font-medium tabular-nums">{{ $progress['hard_skill_score'] !== null ? number_format($progress['hard_skill_score'], 2) : '—' }}</dd></div>
    <div><dt class="text-zinc-500 dark:text-zinc-400">Soft skill</dt><dd class="mt-1 font-medium tabular-nums">{{ $progress['soft_skill_score'] !== null ? number_format($progress['soft_skill_score'], 2) : '—' }}</dd></div>
    <div><dt class="text-zinc-500 dark:text-zinc-400">Laporan dikirim</dt><dd class="mt-1 font-medium tabular-nums">{{ $progress['submitted_count'] }}/{{ $progress['assignment_count'] }}</dd></div>
</dl>
