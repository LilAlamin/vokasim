@props(['logbook', 'showDescription' => true])

<div class="space-y-5">
    <flux:badge :color="$logbook->status->color()">{{ $logbook->status->label() }}</flux:badge>
    @if ($showDescription)
        <div>
            <flux:heading level="3">{{ number_format($logbook->effective_minutes / 60, 2) }} jam kerja efektif</flux:heading>
            <p class="mt-2 whitespace-pre-line break-words text-sm leading-relaxed text-zinc-700 dark:text-zinc-200">{{ $logbook->description }}</p>
        </div>
        @if ($logbook->photo_path)
            <a href="{{ route('internships.photo', $logbook) }}" target="_blank" rel="noopener" class="block text-sm text-blue-700 underline underline-offset-4 dark:text-blue-300">
                <img src="{{ route('internships.photo', $logbook) }}" alt="Dokumentasi aktivitas magang {{ $logbook->work_date->format('d M Y') }}" class="mb-2 max-h-64 w-full rounded-lg object-contain" loading="lazy" />
                Buka foto dokumentasi
            </a>
        @endif
    @endif
    <div class="space-y-3">
        <flux:heading level="3">Riwayat validasi</flux:heading>
        @forelse ($logbook->reviews as $review)
            <div wire:key="review-{{ $review->id }}" class="border-t border-zinc-200 pt-3 text-sm dark:border-zinc-700">
                <p class="font-medium">{{ $review->reviewer->name }} · {{ $review->to_status === \App\Enums\LogbookStatus::Draft ? 'Dikembalikan untuk revisi' : $review->to_status->label() }}</p>
                <p class="mt-1 text-xs text-zinc-600 dark:text-zinc-400">{{ $review->created_at->setTimezone($logbook->placement->timezone)->format('d M Y, H:i') }}</p>
                @if ($review->feedback)<p class="mt-2 whitespace-pre-line break-words text-zinc-700 dark:text-zinc-200">{{ $review->feedback }}</p>@endif
            </div>
        @empty
            <flux:text>Draft belum dikirim ke mentor.</flux:text>
        @endforelse
    </div>
</div>
