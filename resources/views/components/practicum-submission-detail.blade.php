@props(['submission', 'timezone'])
<div {{ $attributes->class(['space-y-4']) }}>
    <div class="flex flex-wrap items-center gap-2"><flux:badge :color="$submission->status === 'graded' ? 'green' : ($submission->status === 'revision_requested' ? 'amber' : 'blue')">{{ $submission->statusLabel() }}</flux:badge><span class="text-sm text-zinc-500 dark:text-zinc-400">Versi {{ $submission->attempt }} · {{ $submission->submitted_at->setTimezone($timezone)->format('d M Y, H:i') }}</span>@if ($submission->is_late)<flux:badge color="amber" size="sm">Melewati tenggat awal</flux:badge>@endif</div>
    @if ($submission->notes)<p class="whitespace-pre-line text-sm leading-6 text-zinc-700 dark:text-zinc-300">{{ $submission->notes }}</p>@endif
    <flux:button :href="route('practicums.submissions.download', $submission)" size="sm">Unduh laporan versi {{ $submission->attempt }}</flux:button>
    @if ($submission->revision_feedback)
        <div class="rounded-lg bg-amber-50 p-4 dark:bg-amber-950/40"><p class="text-sm font-medium text-amber-900 dark:text-amber-200">Catatan revisi</p><p class="mt-2 whitespace-pre-line text-sm leading-6 text-amber-900 dark:text-amber-200">{{ $submission->revision_feedback }}</p>@if ($submission->reviewer)<p class="mt-2 text-sm text-amber-800 dark:text-amber-300">{{ $submission->reviewer->name }}</p>@endif</div>
    @endif
    @if ($submission->assessment)
        <div class="space-y-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">
            <dl class="grid grid-cols-3 gap-3 text-sm"><div><dt class="text-zinc-500 dark:text-zinc-400">Nilai akhir</dt><dd class="mt-1 font-semibold tabular-nums">{{ number_format($submission->assessment->final_score, 2) }} / 100</dd></div><div><dt class="text-zinc-500 dark:text-zinc-400">Hard skill</dt><dd class="mt-1 tabular-nums">{{ number_format($submission->assessment->hard_skill_score, 2) }}</dd></div><div><dt class="text-zinc-500 dark:text-zinc-400">Soft skill</dt><dd class="mt-1 tabular-nums">{{ number_format($submission->assessment->soft_skill_score, 2) }}</dd></div></dl>
            <div class="divide-y divide-zinc-200 text-sm dark:divide-zinc-700">@foreach ($submission->assessment->scores as $score)<div wire:key="assessed-score-{{ $submission->id }}-{{ $loop->index }}" class="flex flex-wrap justify-between gap-2 py-2"><span>{{ $score['title'] }}</span><span class="tabular-nums">{{ $score['score'] }}/{{ $score['max_score'] }} · {{ $score['weight'] }}%</span></div>@endforeach</div>
            <p class="whitespace-pre-line text-sm leading-6 text-zinc-700 dark:text-zinc-300">{{ $submission->assessment->feedback }}</p>
            <flux:text>Dinilai oleh {{ $submission->assessment->assessor->name }} · {{ $submission->assessment->assessed_at->setTimezone($timezone)->format('d M Y, H:i') }}</flux:text>
        </div>
    @endif
</div>
