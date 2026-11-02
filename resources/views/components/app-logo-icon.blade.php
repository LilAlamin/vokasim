<div {{ $attributes->merge(['class' => 'inline-flex items-center justify-center shrink-0']) }}>
    <img src="{{ asset('images/vokasim-icon.png') }}" alt="{{ config('app.name', 'Vokasim') }}" class="h-full w-full object-contain dark:hidden" />
    <img src="{{ asset('images/vokasim-icon-dark.png') }}" alt="{{ config('app.name', 'Vokasim') }}" class="h-full w-full object-contain hidden dark:block" />
</div>
