<?php

use App\Actions\CreateUserPasswordLink;
use App\Actions\DeleteManagedUser;
use App\Actions\SaveManagedUser;
use App\Enums\UserRole;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Administrasi Pengguna')] class extends Component {
    use WithPagination;

    #[Locked]
    public ?int $editingId = null;
    #[Locked]
    public ?string $accessLink = null;
    #[Locked]
    public ?string $linkRecipient = null;
    #[Url]
    public string $search = '';
    #[Url]
    public string $role = '';
    #[Url]
    public string $active = '';
    #[Url]
    public string $studyProgram = '';
    public bool $showEditor = false;
    public array $form = [];

    public function mount(): void
    {
        Gate::authorize('viewAny', User::class);
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->showEditor = false;
        $this->form = ['name' => '', 'email' => '', 'role' => UserRole::Mahasiswa->value, 'identifier_number' => '', 'study_program' => '', 'institution_or_company' => '', 'phone' => '', 'gpa' => '', 'semester' => '', 'graduation_year' => '', 'is_active' => true];
        $this->resetValidation();
    }

    public function createUser(): void
    {
        Gate::authorize('create', User::class);
        $this->resetForm();
        $this->clearAccessLink();
        $this->showEditor = true;
    }

    public function editUser(int $id): void
    {
        $user = User::query()->findOrFail($id);
        Gate::authorize('update', $user);
        $this->resetForm();
        $this->editingId = $user->id;
        $this->form = $user->only(array_keys($this->form));
        $this->form['role'] = $user->role->value;
        $this->showEditor = true;
        $this->clearAccessLink();
    }

    public function save(SaveManagedUser $save): void
    {
        $save->handle(Auth::user(), $this->form, $this->editingId);
        $this->resetForm();
        $this->clearAccessLink();
        $this->resetPage();
        unset($this->users, $this->programs);
        Flux::toast(variant: 'success', text: 'Data pengguna tersimpan.');
    }

    public function deleteUser(int $id, DeleteManagedUser $delete): void
    {
        $delete->handle(Auth::user(), $id);
        $this->clearAccessLink();
        unset($this->users);
        Flux::toast(variant: 'success', text: 'Akun pengguna dihapus.');
    }

    public function createAccessLink(int $id, CreateUserPasswordLink $create): void
    {
        $user = User::query()->findOrFail($id);
        Gate::authorize('update', $user);
        $this->accessLink = $create->handle(Auth::user(), $id);
        $this->linkRecipient = $user->email;
        $this->resetValidation();
        Flux::toast(variant: 'success', text: 'Tautan pengaturan sandi siap disalin.');
    }

    public function clearAccessLink(): void
    {
        $this->accessLink = null;
        $this->linkRecipient = null;
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'role', 'active', 'studyProgram'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function users(): LengthAwarePaginator
    {
        Gate::authorize('viewAny', User::class);

        return User::query()
            ->when($this->search !== '', fn ($query) => $query->where(fn ($query) => $query->where('name', 'like', '%'.mb_substr($this->search, 0, 255).'%')->orWhere('email', 'like', '%'.mb_substr($this->search, 0, 255).'%')->orWhere('identifier_number', 'like', '%'.mb_substr($this->search, 0, 50).'%')))
            ->when($this->role !== '', fn ($query) => $query->where('role', $this->role))
            ->when(in_array($this->active, ['1', '0'], true), fn ($query) => $query->where('is_active', $this->active === '1'))
            ->when($this->studyProgram !== '', fn ($query) => $query->where('study_program', $this->studyProgram))
            ->orderBy('name')->paginate(15);
    }

    #[Computed]
    public function programs(): \Illuminate\Support\Collection
    {
        Gate::authorize('viewAny', User::class);

        return User::query()->whereNotNull('study_program')->where('study_program', '!=', '')->distinct()->orderBy('study_program')->pluck('study_program');
    }
}; ?>

<section class="mx-auto flex w-full max-w-6xl flex-col gap-8">
    <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
        <div><flux:heading size="xl" level="1">Administrasi pengguna</flux:heading><flux:text class="mt-2 max-w-2xl">Kelola akses mahasiswa, dosen, mentor industri, dan administrator beserta data akademiknya.</flux:text></div>
        <flux:button wire:click="createUser" variant="primary">Tambah pengguna</flux:button>
    </header>

    @if ($errors->any())
        <div role="alert" class="rounded-lg bg-red-50 p-4 text-sm text-red-800 dark:bg-red-950 dark:text-red-200"><ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li wire:key="user-error-{{ $loop->index }}">{{ $error }}</li>@endforeach</ul></div>
    @endif

    @if ($accessLink)
        <section class="space-y-4 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700" aria-label="Tautan pengaturan sandi">
            <div><flux:heading level="2">Tautan untuk {{ $linkRecipient }}</flux:heading><flux:text class="mt-1">Salin dan bagikan langsung kepada pemilik akun. Tautan berlaku {{ config('auth.passwords.'.config('fortify.passwords').'.expire', 60) }} menit dan hanya dapat dipakai sekali. Pengguna tetap perlu memverifikasi email jika belum terverifikasi.</flux:text></div>
            <flux:input :value="$accessLink" label="Tautan set / reset sandi" readonly x-on:click="$el.select()" />
            <div class="flex flex-wrap items-center justify-between gap-3"><flux:text>Email tidak dikirim otomatis.</flux:text><flux:button wire:click="clearAccessLink" size="sm">Tutup tautan</flux:button></div>
        </section>
    @endif

    @if ($showEditor)
        <form wire:submit="save" class="space-y-6 rounded-xl border border-zinc-200 p-5 sm:p-6 dark:border-zinc-700">
            <div><flux:heading size="lg" level="2">{{ $editingId ? 'Ubah data pengguna' : 'Pengguna baru' }}</flux:heading><flux:text class="mt-1">{{ $editingId ? 'Mengubah email, role, atau status akun akan mengakhiri sesi login lama.' : 'Setelah akun dibuat, gunakan Buat tautan sandi agar pemilik akun dapat menentukan sandinya sendiri.' }}</flux:text></div>
            <div class="grid gap-5 sm:grid-cols-2">
                <flux:input wire:model="form.name" name="name" label="Nama lengkap" required maxlength="255" autocomplete="off" />
                <flux:input wire:model="form.email" name="email" label="Alamat email" type="email" required maxlength="255" autocomplete="off" />
                <flux:select wire:model.live="form.role" name="role" label="Role pengguna" required>@foreach (UserRole::cases() as $userRole)<option value="{{ $userRole->value }}" wire:key="form-role-{{ $userRole->value }}">{{ $userRole->label() }}</option>@endforeach</flux:select>
                <flux:input wire:model="form.identifier_number" name="identifier_number" label="NIM / NIP / identitas jabatan" maxlength="50" />
                <flux:input wire:model="form.study_program" name="study_program" label="Program studi / bidang ajar" maxlength="100" />
                <flux:input wire:model="form.institution_or_company" name="institution_or_company" label="Institusi / perusahaan" maxlength="150" />
                <flux:input wire:model="form.phone" name="phone" label="Nomor telepon" type="tel" maxlength="25" />
                <div class="self-center"><flux:checkbox wire:model="form.is_active" label="Akun aktif dan dapat masuk" /></div>
            </div>
            @if ($form['role'] === UserRole::Mahasiswa->value)
                <fieldset class="grid gap-5 border-t border-zinc-200 pt-5 sm:grid-cols-3 dark:border-zinc-700">
                    <legend class="px-1 text-sm font-medium">Data akademik mahasiswa</legend>
                    <flux:input wire:model="form.gpa" name="gpa" label="IPK (0,00–4,00)" type="number" min="0" max="4" step="0.01" />
                    <flux:input wire:model="form.semester" name="semester" label="Semester berjalan" type="number" min="1" max="14" />
                    <flux:input wire:model="form.graduation_year" name="graduation_year" label="Tahun lulus / target kelulusan" type="number" min="1950" :max="now()->year + 10" />
                </fieldset>
            @endif
            <div class="flex flex-wrap gap-3"><flux:button type="submit" variant="primary">Simpan pengguna</flux:button><flux:button wire:click="resetForm">Batal</flux:button></div>
        </form>
    @endif

    <div class="grid items-end gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <flux:input wire:model.live.debounce.350ms="search" label="Cari pengguna" placeholder="Nama, email, atau NIM / NIP" type="search" />
        <flux:select wire:model.live="role" label="Role"><option value="">Semua role</option>@foreach (UserRole::cases() as $userRole)<option value="{{ $userRole->value }}" wire:key="filter-role-{{ $userRole->value }}">{{ $userRole->label() }}</option>@endforeach</flux:select>
        <flux:select wire:model.live="active" label="Status akun"><option value="">Semua status</option><option value="1">Aktif</option><option value="0">Nonaktif</option></flux:select>
        <flux:select wire:model.live="studyProgram" label="Program studi"><option value="">Semua program studi</option>@foreach ($this->programs as $program)<option value="{{ $program }}" wire:key="user-program-{{ $loop->index }}">{{ $program }}</option>@endforeach</flux:select>
    </div>

    <div class="divide-y divide-zinc-200 dark:divide-zinc-700" wire:loading.class="opacity-60">
        @forelse ($this->users as $user)
            <article wire:key="managed-user-{{ $user->id }}" class="flex flex-col justify-between gap-4 py-5 first:pt-0 lg:flex-row">
                <div class="min-w-0 space-y-2">
                    <div class="flex flex-wrap items-center gap-2"><flux:heading level="2">{{ $user->name }}</flux:heading><flux:badge :color="$user->is_active ? 'green' : 'zinc'" size="sm">{{ $user->is_active ? 'Aktif' : 'Nonaktif' }}</flux:badge></div>
                    <flux:text class="break-words">{{ $user->email }} · {{ $user->role->label() }}</flux:text>
                    <flux:text>{{ $user->identifier_number ?: 'Identitas belum diisi' }} · {{ $user->study_program ?: $user->institution_or_company ?: 'Institusi belum diisi' }}</flux:text>
                    @if ($user->isMahasiswa())<p class="text-sm text-zinc-500 dark:text-zinc-400">IPK {{ $user->gpa !== null ? number_format($user->gpa, 2) : 'belum diisi' }} · Semester {{ $user->semester ?: 'belum diisi' }} · Tahun lulus {{ $user->graduation_year ?: 'belum diisi' }}</p>@endif
                    <flux:text>{{ $user->hasVerifiedEmail() ? 'Email terverifikasi' : 'Email belum terverifikasi' }}</flux:text>
                </div>
                <div class="flex shrink-0 flex-wrap items-start gap-2">
                    <flux:button wire:click="editUser({{ $user->id }})" size="sm">Ubah</flux:button>
                    @if ($user->is_active)<flux:button wire:click="createAccessLink({{ $user->id }})" size="sm">Buat tautan sandi</flux:button>@endif
                    <flux:button wire:click="deleteUser({{ $user->id }})" wire:confirm="Hapus akun ini secara permanen? Akun dengan riwayat akademik harus dinonaktifkan melalui formulir Ubah." variant="ghost" size="sm">Hapus</flux:button>
                </div>
            </article>
        @empty
            <div class="rounded-xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-700"><flux:heading level="2">Tidak ada pengguna yang sesuai</flux:heading><flux:text class="mt-2">Ubah pencarian atau filter, atau tambahkan akun pengguna baru.</flux:text></div>
        @endforelse
    </div>
    {{ $this->users->links() }}
</section>
