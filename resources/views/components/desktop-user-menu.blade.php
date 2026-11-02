<flux:dropdown position="bottom" align="start">
    <flux:sidebar.profile
        :name="auth()->user()->name"
        :initials="auth()->user()->initials()"
        icon:trailing="chevrons-up-down"
        data-test="sidebar-menu-button"
    />

    <flux:menu class="w-64">
        <div class="flex items-center gap-2.5 px-2 py-2 text-start text-sm">
            <flux:avatar
                :name="auth()->user()->name"
                :initials="auth()->user()->initials()"
            />
            <div class="grid flex-1 text-start text-sm leading-tight">
                <flux:heading class="truncate font-semibold">{{ auth()->user()->name }}</flux:heading>
                <div class="flex items-center gap-1.5 mt-0.5">
                    <flux:badge size="sm" :color="auth()->user()->role->badgeColor()">
                        {{ auth()->user()->role->label() }}
                    </flux:badge>
                </div>
            </div>
        </div>

        @if(app()->environment('local'))
            <flux:menu.separator />
            <div class="px-2 py-1 text-xs font-medium text-zinc-600 dark:text-zinc-300">
                Ganti peran untuk demo lokal
            </div>
            @foreach (\App\Enums\UserRole::cases() as $previewRole)
                <form method="POST" action="{{ route('switch-role', $previewRole->value) }}">
                    @csrf
                    <flux:menu.item as="button" type="submit">{{ $previewRole->label() }}</flux:menu.item>
                </form>
            @endforeach
        @endif

        <flux:menu.separator />
        <flux:menu.radio.group>
            <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                {{ __('Settings') }}
            </flux:menu.item>
            <form method="POST" action="{{ route('logout') }}" class="w-full">
                @csrf
                <flux:menu.item
                    as="button"
                    type="submit"
                    icon="arrow-right-start-on-rectangle"
                    class="w-full cursor-pointer text-red-600 dark:text-red-400 hover:text-red-700"
                    data-test="logout-button"
                >
                    {{ __('Log out') }}
                </flux:menu.item>
            </form>
        </flux:menu.radio.group>
    </flux:menu>
</flux:dropdown>
