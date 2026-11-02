<x-layouts::app :title="__('Dashboard') . ' - ' . auth()->user()->role->label()">
    <div class="flex flex-col gap-6 w-full">
        <!-- Local Dev Role Preview Bar (Memudahkan pengujian tampilan tiap role) -->
        @if(app()->environment('local'))
            <div class="flex flex-wrap items-center justify-between gap-3 p-3 px-4 rounded-xl bg-zinc-100 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 text-xs">
                <div class="flex items-center gap-2">
                    <span class="font-bold text-zinc-700 dark:text-zinc-300">Mode Pratinjau Role:</span>
                    <span class="px-2 py-0.5 rounded font-semibold bg-zinc-200 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200 border border-zinc-300 dark:border-zinc-700">
                        {{ auth()->user()->role->label() }}
                    </span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="text-zinc-500 mr-1 hidden sm:inline">Uji Tampilan Role:</span>
                    @foreach (\App\Enums\UserRole::cases() as $previewRole)
                        <form method="POST" action="{{ route('switch-role', $previewRole->value) }}">
                            @csrf
                            <flux:button size="sm" type="submit">{{ $previewRole->label() }}</flux:button>
                        </form>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <div><flux:heading>E-Logbook & Presensi Magang</flux:heading><flux:text class="mt-1">Kelola aktivitas magang dan verifikasi pembimbing.</flux:text></div>
            <flux:button variant="primary" :href="route(auth()->user()->isAdmin() ? 'internships.placements' : (auth()->user()->isMahasiswa() ? 'internships.logbook' : 'internships.reviews'))" wire:navigate>
                {{ auth()->user()->isAdmin() ? 'Atur penempatan magang' : (auth()->user()->isMahasiswa() ? 'Buka logbook & presensi' : 'Buka validasi logbook') }}
            </flux:button>
        </div>

        <!-- Dynamic Role-Specific Dashboard Content -->
        @if(auth()->user()->isMahasiswa())
            @include('dashboard.mahasiswa')
        @elseif(auth()->user()->isDosen())
            @include('dashboard.dosen')
        @elseif(auth()->user()->isMentorDudi())
            @include('dashboard.mentor')
        @else
            @include('dashboard.admin')
        @endif
    </div>
</x-layouts::app>
