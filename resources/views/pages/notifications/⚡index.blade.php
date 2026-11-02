<?php

use App\Enums\NotificationCategory;
use Flux\Flux;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Pusat Notifikasi')] class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $filter = '';

    #[Url(except: '')]
    public string $category = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['filter', 'category'], true)) {
            $this->resetPage();
        }
    }

    public function markRead(string $id): void
    {
        $notice = Auth::user()->notifications()->whereKey($id)->firstOrFail();
        $notice->markAsRead();
        $this->resetPage();
        unset($this->entries, $this->unreadCount);
    }

    public function markAllRead(): void
    {
        Auth::user()->unreadNotifications()->update(['read_at' => now()]);
        $this->resetPage();
        unset($this->entries, $this->unreadCount);
        Flux::toast(variant: 'success', text: 'Semua notifikasi Anda ditandai sudah dibaca.');
    }

    public function openNotification(string $id): void
    {
        $notice = Auth::user()->notifications()->whereKey($id)->firstOrFail();
        $name = $notice->data['route'] ?? null;
        $parameters = $notice->data['parameters'] ?? [];
        abort_unless(is_string($name) && Route::has($name) && is_array($parameters), 404);
        $notice->markAsRead();
        $this->redirectRoute($name, $parameters, navigate: true);
    }

    #[Computed]
    public function unreadCount(): int
    {
        return Auth::user()->unreadNotifications()->count();
    }

    #[Computed]
    public function entries(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = Auth::user()->notifications();

        if ($this->filter === 'unread') {
            $query->whereNull('read_at');
        }

        if (NotificationCategory::tryFrom($this->category) !== null) {
            $query->where('data->category', $this->category);
        }

        return $query->reorder()->orderByDesc('created_at')->orderByDesc('id')->paginate(15)->through(function (DatabaseNotification $notice): array {
            $category = $notice->data['category'] ?? '';
            $route = $notice->data['route'] ?? null;

            return [
                'id' => $notice->id,
                'title' => is_string($notice->data['title'] ?? null) ? $notice->data['title'] : 'Pembaruan akun',
                'message' => is_string($notice->data['message'] ?? null) ? $notice->data['message'] : '',
                'category' => is_string($category) ? NotificationCategory::tryFrom($category)?->label() ?? 'Pembaruan' : 'Pembaruan',
                'unread' => $notice->read_at === null,
                'date' => $notice->created_at->timezone(config('app.timezone'))->format('d M Y H:i T'),
                'has_link' => is_string($route) && Route::has($route),
            ];
        });
    }
}; ?>

<section class="mx-auto flex w-full max-w-4xl flex-col gap-6" wire:poll.60s.visible>
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">Pusat Notifikasi</flux:heading>
            <flux:text class="mt-2">{{ $this->unreadCount }} belum dibaca. Ikuti tenggat, keputusan pembimbing, dan perkembangan kegiatan Anda.</flux:text>
        </div>
        <flux:button wire:click="markAllRead" :disabled="$this->unreadCount === 0" wire:loading.attr="disabled" wire:target="markAllRead">Tandai semua sudah dibaca</flux:button>
    </header>
    <div class="grid gap-3 sm:grid-cols-2">
        <flux:select wire:model.live="filter" label="Tampilkan">
            <option value="">Semua notifikasi</option>
            <option value="unread">Belum dibaca</option>
        </flux:select>
        <flux:select wire:model.live="category" label="Kegiatan">
            <option value="">Semua kegiatan</option>
            @foreach (NotificationCategory::cases() as $option)<option wire:key="notice-category-{{ $option->value }}" value="{{ $option->value }}">{{ $option->label() }}</option>@endforeach
        </flux:select>
    </div>
    <div class="flex flex-col divide-y divide-zinc-200 border-y border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
        @forelse ($this->entries as $entry)
            <article wire:key="notification-{{ $entry['id'] }}" class="flex min-w-0 flex-col gap-3 py-5">
                <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
                    <div class="flex items-center gap-2"><span class="text-zinc-600 dark:text-zinc-400">{{ $entry['category'] }}</span>@if ($entry['unread'])<flux:badge color="blue" size="sm">Belum dibaca</flux:badge>@endif</div>
                    <time class="text-zinc-600 dark:text-zinc-400">{{ $entry['date'] }}</time>
                </div>
                <div class="flex flex-col gap-2">
                    <flux:heading level="2" class="break-words">{{ $entry['title'] }}</flux:heading>
                    <p class="max-w-prose break-words text-sm leading-relaxed text-zinc-700 dark:text-zinc-300">{{ $entry['message'] }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if ($entry['has_link'])<flux:button wire:click="openNotification('{{ $entry['id'] }}')" size="sm" wire:loading.attr="disabled" wire:target="openNotification">Lihat pembaruan</flux:button>@endif
                    @if ($entry['unread'])<flux:button wire:click="markRead('{{ $entry['id'] }}')" variant="ghost" size="sm" wire:loading.attr="disabled" wire:target="markRead">Tandai sudah dibaca</flux:button>@endif
                </div>
            </article>
        @empty
            <div class="flex flex-col gap-3 py-12">
                <flux:heading size="lg" level="2">{{ $filter === 'unread' ? 'Semua pembaruan sudah terbaca' : 'Belum ada notifikasi' }}</flux:heading>
                <flux:text>{{ $filter !== '' || $category !== '' ? 'Pilih semua notifikasi atau kegiatan untuk melihat riwayat lainnya.' : 'Pengingat tenggat dan perubahan status kegiatan yang terkait dengan Anda akan tampil di sini.' }}</flux:text>
            </div>
        @endforelse
    </div>
    <p wire:loading wire:target="markRead,markAllRead,openNotification" role="status" class="text-sm text-zinc-600 dark:text-zinc-300">Memperbarui notifikasi…</p>
    {{ $this->entries->links() }}
</section>
