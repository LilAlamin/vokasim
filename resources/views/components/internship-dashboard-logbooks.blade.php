@props(['entries', 'heading'])

<div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <flux:heading level="2">{{ $heading }}</flux:heading>
        <flux:button size="sm" :href="route(auth()->user()->isMahasiswa() ? 'internships.logbook' : 'internships.reviews')" wire:navigate>Lihat semua</flux:button>
    </div>
    <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
        @forelse ($entries as $entry)
            <div class="space-y-2 py-3 first:pt-0 last:pb-0">
                @if (! auth()->user()->isMahasiswa())<flux:heading>{{ $entry->placement->student->name }}</flux:heading>@endif
                <p class="line-clamp-2 break-words text-sm text-zinc-700 dark:text-zinc-200">{{ $entry->description ?: 'Draft belum dilengkapi' }}</p>
                <div class="flex flex-wrap items-center gap-3 text-xs text-zinc-600 dark:text-zinc-400">
                    <span>{{ $entry->work_date->format('d M Y') }} · {{ number_format($entry->effective_minutes / 60, 2) }} jam efektif</span>
                    <flux:badge :color="$entry->status->color()" size="sm">{{ $entry->status->label() }}</flux:badge>
                </div>
            </div>
        @empty
            <flux:text>{{ auth()->user()->isMahasiswa() ? 'Belum ada logbook. Catat aktivitas pertama melalui menu E-Logbook Magang.' : 'Belum ada logbook yang menunggu validasi Anda.' }}</flux:text>
        @endforelse
    </div>
</div>
