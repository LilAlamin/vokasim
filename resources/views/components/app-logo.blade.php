@props([
    'sidebar' => false,
])

<a href="{{ $attributes->get('href', route('dashboard')) }}" {{ $attributes->merge(['class' => 'flex items-center gap-2 py-1 focus:outline-none']) }} wire:navigate>
    <!-- Full Logo for Expanded Sidebar & Navbar (Light Mode) -->
    <img 
        src="{{ asset('images/vokasim-logo.png') }}" 
        alt="{{ config('app.name', 'Vokasim') }}" 
        class="h-9 w-auto max-w-[180px] object-contain dark:hidden in-data-flux-sidebar-collapsed-desktop:hidden" 
    />

    <!-- Full Logo for Expanded Sidebar & Navbar (Dark Mode) -->
    <img 
        src="{{ asset('images/vokasim-logo-dark.png') }}" 
        alt="{{ config('app.name', 'Vokasim') }}" 
        class="h-9 w-auto max-w-[180px] object-contain hidden dark:block in-data-flux-sidebar-collapsed-desktop:hidden" 
    />

    @if($sidebar)
        <!-- Square Emblem Icon when Sidebar is Collapsed on Desktop (Light Mode) -->
        <img 
            src="{{ asset('images/vokasim-icon.png') }}" 
            alt="{{ config('app.name', 'Vokasim') }}" 
            class="size-7 object-contain hidden in-data-flux-sidebar-collapsed-desktop:block in-data-flux-sidebar-collapsed-desktop:dark:hidden" 
        />

        <!-- Square Emblem Icon when Sidebar is Collapsed on Desktop (Dark Mode) -->
        <img 
            src="{{ asset('images/vokasim-icon-dark.png') }}" 
            alt="{{ config('app.name', 'Vokasim') }}" 
            class="size-7 object-contain hidden in-data-flux-sidebar-collapsed-desktop:dark:block" 
        />
    @endif

    <span class="sr-only">{{ config('app.name', 'Vokasim') }}</span>
</a>
