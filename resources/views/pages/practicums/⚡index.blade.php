<?php

use App\Actions\SavePracticumModule;
use App\Enums\UserRole;
use App\Models\PracticumModule;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Katalog Praktikum')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';
    #[Url]
    public string $semester = '';
    #[Url]
    public string $studyProgram = '';
    #[Url]
    public string $skillArea = '';
    #[Locked]
    public ?int $editingId = null;
    public bool $showEditor = false;
    public array $form = [];

    public function mount(): void
    {
        Gate::authorize('viewAny', PracticumModule::class);
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->showEditor = false;
        $this->form = ['lecturer_id' => Auth::id(), 'code' => '', 'title' => '', 'study_program' => '', 'semester' => 1, 'skill_area' => '', 'description' => '', 'timezone' => 'Asia/Jakarta', 'starts_on' => now()->toDateString(), 'ends_on' => now()->addMonths(6)->toDateString(), 'published' => false];
        $this->resetValidation();
    }

    public function createModule(): void
    {
        Gate::authorize('create', PracticumModule::class);
        $this->resetForm();
        $this->showEditor = true;
    }

    public function editModule(int $id): void
    {
        $module = PracticumModule::query()->forUser(Auth::user())->findOrFail($id);
        Gate::authorize('manage', $module);
        $this->editingId = $module->id;
        $this->form = $module->only(array_keys($this->form));
        $this->form['starts_on'] = $module->starts_on->toDateString();
        $this->form['ends_on'] = $module->ends_on->toDateString();
        $this->showEditor = true;
        $this->resetValidation();
    }

    public function save(SavePracticumModule $save): void
    {
        $save->handle(Auth::user(), $this->form, $this->editingId);
        $this->resetForm();
        $this->resetPage();
        unset($this->modules, $this->filterOptions);
        Flux::toast(variant: 'success', text: 'Kelas praktikum tersimpan.');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'semester', 'studyProgram', 'skillArea'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function modules(): LengthAwarePaginator
    {
        Gate::authorize('viewAny', PracticumModule::class);

        return PracticumModule::query()->forUser(Auth::user())->with('lecturer')->withCount(['assignments', 'materials', 'enrollments' => fn ($query) => $query->where('status', 'active')])
            ->when($this->search !== '', fn ($query) => $query->where(fn ($query) => $query->where('title', 'like', '%'.mb_substr($this->search, 0, 255).'%')->orWhere('code', 'like', '%'.mb_substr($this->search, 0, 80).'%')))
            ->when($this->semester !== '', fn ($query) => $query->where('semester', $this->semester))
            ->when($this->studyProgram !== '', fn ($query) => $query->where('study_program', $this->studyProgram))
            ->when($this->skillArea !== '', fn ($query) => $query->where('skill_area', $this->skillArea))
            ->latest('starts_on')->paginate(12);
    }

    #[Computed]
    public function filterOptions(): Collection
    {
        return PracticumModule::query()->forUser(Auth::user())->get(['study_program', 'skill_area']);
    }

    #[Computed]
    public function lecturers(): Collection
    {
        Gate::authorize('create', PracticumModule::class);

        return User::query()->where('role', UserRole::Dosen)->orderBy('name')->get(['id', 'name']);
    }
}; ?>

<section class="mx-auto flex w-full max-w-6xl flex-col gap-8">
    <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
        <div>
            <flux:heading size="xl" level="1">Katalog praktikum</flux:heading>
            <flux:text class="mt-2 max-w-2xl">{{ auth()->user()->isMahasiswa() ? 'Kelas yang Anda ikuti, panduan keselamatan, jadwal laboratorium, dan laporan praktik dalam satu tempat.' : 'Kelola kelas yang Anda ampu, tetapkan peserta, dan pantau keterampilan melalui penilaian praktik.' }}</flux:text>
        </div>
        @can('create', PracticumModule::class)
            <flux:button wire:click="createModule" variant="primary">Tambah kelas</flux:button>
        @endcan
    </header>

    @if ($showEditor)
        <form wire:submit="save" class="rounded-xl border border-zinc-200 p-5 sm:p-6 dark:border-zinc-700">
            <flux:heading size="lg" level="2">{{ $editingId ? 'Ubah kelas' : 'Kelas praktikum baru' }}</flux:heading>
            <flux:text class="mt-1">Simpan sebagai draf saat menyiapkan materi. Terbitkan kelas agar peserta dapat membukanya.</flux:text>
            @if ($errors->any())
                <div role="alert" class="mt-4 rounded-lg bg-red-50 p-4 text-sm text-red-800 dark:bg-red-950 dark:text-red-200"><ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li wire:key="module-error-{{ $loop->index }}">{{ $error }}</li>@endforeach</ul></div>
            @endif
            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <flux:input wire:model="form.title" name="title" label="Nama kelas / modul" required maxlength="255" />
                <flux:input wire:model="form.code" name="code" label="Kode kelas dan periode" placeholder="TM-301-A-2026" required maxlength="80" />
                <flux:input wire:model="form.study_program" name="study_program" label="Program studi" required maxlength="255" />
                <flux:input wire:model="form.skill_area" name="skill_area" label="Bidang keahlian" placeholder="Manufaktur, jaringan, atau tata boga" required maxlength="255" />
                <flux:input wire:model="form.semester" name="semester" label="Semester" type="number" min="1" max="14" required />
                <flux:select wire:model="form.timezone" label="Zona waktu kelas">
                    <option value="Asia/Jakarta">WIB</option><option value="Asia/Makassar">WITA</option><option value="Asia/Jayapura">WIT</option>
                </flux:select>
                <flux:input wire:model="form.starts_on" name="starts_on" label="Tanggal mulai" type="date" required />
                <flux:input wire:model="form.ends_on" name="ends_on" label="Tanggal selesai" type="date" required />
                @if (auth()->user()->isAdmin())
                    <flux:select wire:model="form.lecturer_id" name="lecturer_id" label="Dosen pengampu" required>
                        <option value="">Pilih dosen</option>
                        @foreach ($this->lecturers as $lecturer)<option value="{{ $lecturer->id }}" wire:key="lecturer-{{ $lecturer->id }}">{{ $lecturer->name }}</option>@endforeach
                    </flux:select>
                @endif
            </div>
            <div class="mt-5"><flux:textarea wire:model="form.description" name="description" label="Deskripsi dan capaian pembelajaran" rows="3" required maxlength="10000" /></div>
            <div class="mt-5"><flux:checkbox wire:model="form.published" label="Terbitkan untuk peserta yang terdaftar" /></div>
            <div class="mt-6 flex flex-wrap gap-3">
                <flux:button variant="primary" type="submit">Simpan kelas</flux:button>
                <flux:button wire:click="resetForm">Batal</flux:button>
            </div>
        </form>
    @endif

    <div class="grid items-end gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <flux:input wire:model.live.debounce.350ms="search" label="Cari kelas" placeholder="Nama atau kode kelas" type="search" />
        <flux:select wire:model.live="semester" label="Semester">
            <option value="">Semua semester</option>
            @for ($number = 1; $number <= 14; $number++)<option value="{{ $number }}" wire:key="semester-{{ $number }}">Semester {{ $number }}</option>@endfor
        </flux:select>
        <flux:select wire:model.live="studyProgram" label="Program studi">
            <option value="">Semua program studi</option>
            @foreach ($this->filterOptions->pluck('study_program')->unique()->sort() as $program)<option value="{{ $program }}" wire:key="program-{{ $loop->index }}">{{ $program }}</option>@endforeach
        </flux:select>
        <flux:select wire:model.live="skillArea" label="Bidang keahlian">
            <option value="">Semua bidang</option>
            @foreach ($this->filterOptions->pluck('skill_area')->unique()->sort() as $area)<option value="{{ $area }}" wire:key="area-{{ $loop->index }}">{{ $area }}</option>@endforeach
        </flux:select>
    </div>

    <div class="divide-y divide-zinc-200 dark:divide-zinc-700" wire:loading.class="opacity-60">
        @forelse ($this->modules as $module)
            <article wire:key="module-{{ $module->id }}" class="flex flex-col justify-between gap-5 py-6 first:pt-0 sm:flex-row">
                <div class="min-w-0 space-y-2">
                    <div class="flex flex-wrap items-center gap-3">
                        <flux:heading size="lg" level="2"><a href="{{ route('practicums.show', $module) }}" wire:navigate class="hover:underline">{{ $module->title }}</a></flux:heading>
                        @if (! $module->published)<flux:badge size="sm">Draf</flux:badge>@endif
                    </div>
                    <flux:text>{{ $module->code }} · Semester {{ $module->semester }} · {{ $module->study_program }}</flux:text>
                    <p class="max-w-2xl text-sm leading-6 text-zinc-600 dark:text-zinc-400">{{ \Illuminate\Support\Str::limit($module->description, 180) }}</p>
                    <flux:text>{{ $module->lecturer->name }} · {{ $module->skill_area }}</flux:text>
                    <div class="flex flex-wrap gap-x-5 gap-y-1 text-sm text-zinc-500 dark:text-zinc-400">
                        <span>{{ $module->enrollments_count }} peserta</span><span>{{ $module->assignments_count }} tugas</span><span>{{ $module->materials_count }} materi</span><span>{{ $module->starts_on->format('d M') }} – {{ $module->ends_on->format('d M Y') }}</span>
                    </div>
                </div>
                <div class="flex shrink-0 flex-wrap items-start gap-2">
                    <flux:button :href="route('practicums.show', $module)" wire:navigate>Buka kelas</flux:button>
                    @can('manage', $module)<flux:button wire:click="editModule({{ $module->id }})" variant="ghost">Ubah</flux:button>@endcan
                </div>
            </article>
        @empty
            <div class="rounded-xl border border-dashed border-zinc-300 px-6 py-12 text-center dark:border-zinc-700">
                <flux:heading level="2">Belum ada kelas yang sesuai</flux:heading>
                <flux:text class="mt-2">{{ auth()->user()->isMahasiswa() ? 'Coba ubah filter. Jika kelas belum muncul, hubungi dosen untuk memastikan Anda terdaftar dan kelas sudah diterbitkan.' : 'Coba ubah filter atau tambahkan kelas praktikum untuk mulai menyusun materi dan jadwal.' }}</flux:text>
            </div>
        @endforelse
    </div>
    {{ $this->modules->links() }}
</section>
