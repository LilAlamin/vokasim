<x-portfolio-public-layout :title="$portfolio['title']">
    <article class="flex flex-col gap-7">
        <header class="flex flex-col items-start gap-4">
            <flux:badge :color="$portfolio['is_published'] ? 'blue' : 'zinc'">{{ $portfolio['is_published'] ? 'Proyek dibagikan' : 'Pratinjau privat' }}</flux:badge>
            <flux:heading size="xl" level="1" class="max-w-3xl break-words">{{ $portfolio['title'] }}</flux:heading>
            <p class="text-zinc-600 dark:text-zinc-300">{{ $portfolio['author_name'] }}@if ($portfolio['completed_on']) · Selesai {{ $portfolio['completed_on'] }}@endif</p>
        </header>
        <p class="max-w-prose whitespace-pre-line break-words leading-relaxed">{{ $portfolio['description'] }}</p>
        <section class="flex flex-col gap-3 border-t border-zinc-200 pt-6 dark:border-zinc-800">
            <flux:heading level="2">Kompetensi yang digunakan</flux:heading>
            <div class="flex flex-wrap gap-2">@foreach ($portfolio['skills'] as $skill)<flux:badge>{{ $skill }}</flux:badge>@endforeach</div>
            <flux:text>Kompetensi dan uraian karya ditulis oleh pemilik portofolio.</flux:text>
        </section>
        <div class="flex flex-wrap gap-3">
            @if ($portfolio['project_url'])<flux:button :href="$portfolio['project_url']" target="_blank" rel="noopener noreferrer" variant="primary">Buka hasil proyek</flux:button>@endif
            @if ($evidenceUrl)<flux:button :href="$evidenceUrl">Unduh bukti privat</flux:button>@endif
            @auth<flux:button :href="route('portfolios.index')" wire:navigate>Kembali ke portofolio</flux:button>@endauth
        </div>
    </article>
</x-portfolio-public-layout>
