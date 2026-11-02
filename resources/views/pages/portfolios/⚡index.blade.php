<?php

use App\Actions\SavePortfolioProject;
use App\Models\PortfolioProject;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Title('Portofolio Proyek')] class extends Component
{
    use WithFileUploads, WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $publication = '';

    #[Locked]
    public ?int $editingId = null;

    public bool $showEditor = false;

    public string $title = '';

    public string $description = '';

    public string $skills = '';

    public string $projectUrl = '';

    public string $completedOn = '';

    public bool $isPublished = false;

    public bool $removeEvidence = false;

    public $evidence = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', PortfolioProject::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'publication'], true)) {
            $this->resetPage();
        }
    }

    public function createProject(): void
    {
        Gate::authorize('create', PortfolioProject::class);
        $this->cancelEditor();
        $this->showEditor = true;
    }

    public function editProject(int $id): void
    {
        $project = PortfolioProject::query()->forUser(Auth::user())->findOrFail($id);
        Gate::authorize('update', $project);
        $this->cancelEditor();
        $this->editingId = $project->id;
        $this->title = $project->title;
        $this->description = $project->description;
        $this->skills = implode(', ', $project->skills);
        $this->projectUrl = $project->project_url ?? '';
        $this->completedOn = $project->completed_on?->toDateString() ?? '';
        $this->isPublished = $project->is_published;
        $this->showEditor = true;
    }

    public function cancelEditor(): void
    {
        $this->reset('editingId', 'showEditor', 'title', 'description', 'skills', 'projectUrl', 'completedOn', 'isPublished', 'removeEvidence', 'evidence');
        $this->resetValidation();
    }

    public function saveProject(SavePortfolioProject $save): void
    {
        $save->handle(Auth::user(), $this->editingId, [
            'title' => $this->title, 'description' => $this->description, 'skills' => explode(',', $this->skills),
            'project_url' => $this->projectUrl, 'completed_on' => $this->completedOn,
            'is_published' => $this->isPublished, 'remove_evidence' => $this->removeEvidence,
        ], $this->evidence);

        if ($this->evidence instanceof TemporaryUploadedFile) {
            $this->evidence->delete();
        }

        $this->cancelEditor();
        $this->resetPage();
        unset($this->projects);
        Flux::toast(variant: 'success', text: 'Proyek tersimpan di portofolio.');
    }

    public function deleteProject(int $id, SavePortfolioProject $save): void
    {
        $save->delete(Auth::user(), $id);

        if ($this->editingId === $id) {
            $this->cancelEditor();
        }

        $this->resetPage();
        unset($this->projects);
        Flux::toast(variant: 'success', text: 'Proyek dan bukti unggahannya dihapus.');
    }

    #[Computed]
    public function projects(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        Gate::authorize('viewAny', PortfolioProject::class);
        $query = PortfolioProject::query()->forUser(Auth::user())->with('student:id,name');

        if ($this->search !== '') {
            $query->where('title', 'like', '%'.Str::limit($this->search, 100, '').'%');
        }

        if (in_array($this->publication, ['published', 'private'], true)) {
            $query->where('is_published', $this->publication === 'published');
        }

        return $query->orderByDesc('created_at')->orderByDesc('id')->paginate(9);
    }
}; ?>

<section class="mx-auto flex w-full max-w-6xl flex-col gap-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div class="max-w-2xl">
            <flux:heading size="xl" level="1">{{ auth()->user()->isMahasiswa() ? 'Portofolio Proyek' : 'Portofolio Mahasiswa' }}</flux:heading>
            <flux:text class="mt-2">{{ auth()->user()->isMahasiswa() ? 'Tunjukkan proses, keterampilan, dan hasil karya yang membentuk pengalaman praktik Anda.' : 'Tinjau karya mahasiswa yang berada dalam lingkup bimbingan Anda.' }}</flux:text>
        </div>
        <div class="flex flex-wrap gap-2">
            <flux:button :href="route('certificates.index')" wire:navigate>Sertifikat</flux:button>
            @can('create', PortfolioProject::class)
                <flux:button wire:click="createProject" variant="primary">Tambah proyek</flux:button>
            @endcan
        </div>
    </header>

    @if ($showEditor)
        <form wire:submit="saveProject" class="flex flex-col gap-5 rounded-xl border border-zinc-200 p-5 sm:p-6 dark:border-zinc-700">
            <flux:heading size="lg" level="2">{{ $editingId ? 'Edit proyek' : 'Proyek baru' }}</flux:heading>
            @if ($errors->any())
                <div role="alert" class="rounded-lg bg-red-50 p-4 text-sm text-red-800 dark:bg-red-950 dark:text-red-200">
                    <ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li wire:key="portfolio-error-{{ $loop->index }}">{{ $error }}</li>@endforeach</ul>
                </div>
            @endif
            <flux:input wire:model="title" label="Judul proyek" maxlength="160" required />
            <flux:textarea wire:model="description" label="Proses dan hasil" description="Ceritakan masalah yang diselesaikan, peran Anda, alat yang digunakan, dan hasilnya." rows="5" maxlength="10000" required />
            <flux:input wire:model="skills" label="Kompetensi" description="Pisahkan dengan koma, maksimal 12. Contoh: CNC, gambar teknik, K3. Tag ini merupakan pernyataan Anda sendiri." required />
            <div class="grid gap-4 sm:grid-cols-2">
                <div><flux:input wire:model="projectUrl" label="Tautan demo atau repositori (opsional)" type="url" placeholder="https://" /><flux:error name="project_url" /></div>
                <div><flux:input wire:model="completedOn" label="Tanggal selesai (opsional)" type="date" /><flux:error name="completed_on" /></div>
            </div>
            <flux:input wire:model="evidence" type="file" label="Bukti hasil karya (opsional)" description="PDF, JPG, PNG, atau WebP, maksimal 10 MB. Bukti hanya dapat diakses oleh Anda, dosen pembimbing, dan administrator." accept="application/pdf,image/jpeg,image/png,image/webp" />
            @if ($editingId)
                <flux:checkbox wire:model="removeEvidence" label="Hapus bukti sebelumnya" description="Jika memilih berkas baru, berkas baru akan menggantikan bukti sebelumnya." />
            @endif
            <flux:checkbox wire:model="isPublished" label="Bagikan proyek melalui tautan publik" description="Nama, judul, uraian, kompetensi, tanggal, dan tautan proyek dapat dilihat siapa pun yang memiliki tautannya. Berkas bukti tetap privat. Matikan pilihan ini untuk menghentikan berbagi." />
            <div class="flex flex-wrap gap-3">
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="saveProject,evidence">Simpan proyek</flux:button>
                <flux:button wire:click="cancelEditor" wire:loading.attr="disabled" wire:target="saveProject">Batal</flux:button>
            </div>
            <p wire:loading wire:target="evidence,saveProject" role="status" class="text-sm text-zinc-600 dark:text-zinc-300">Menyimpan proyek dan bukti unggahan…</p>
        </form>
    @endif

    <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_14rem]">
        <flux:input wire:model.live.debounce.300ms="search" label="Cari proyek" placeholder="Judul proyek" type="search" />
        <flux:select wire:model.live="publication" label="Akses proyek">
            <option value="">Semua proyek</option>
            <option value="private">Privat</option>
            <option value="published">Dibagikan</option>
        </flux:select>
    </div>

    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($this->projects as $project)
            <article wire:key="portfolio-{{ $project->id }}" class="flex min-w-0 flex-col gap-4 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <flux:badge :color="$project->is_published ? 'blue' : 'zinc'" size="sm">{{ $project->is_published ? 'Dibagikan' : 'Privat' }}</flux:badge>
                    @if ($project->completed_on)<span class="text-sm text-zinc-600 dark:text-zinc-400">{{ $project->completed_on->format('d M Y') }}</span>@endif
                </div>
                <div>
                    <flux:heading size="lg" level="2" class="break-words">{{ $project->title }}</flux:heading>
                    @unless (auth()->user()->isMahasiswa())<flux:text class="mt-1">{{ $project->student->name }}</flux:text>@endunless
                </div>
                <p class="line-clamp-3 break-words text-sm leading-relaxed text-zinc-600 dark:text-zinc-300">{{ $project->description }}</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($project->skills as $skill)<flux:badge wire:key="portfolio-skill-{{ $project->id }}-{{ $loop->index }}" size="sm">{{ $skill }}</flux:badge>@endforeach
                </div>
                @if ($project->evidence_path)<span class="text-sm text-zinc-600 dark:text-zinc-400">Bukti karya terlampir · akses terbatas</span>@endif
                <div class="mt-auto flex flex-wrap gap-2 pt-2">
                    <flux:button :href="route('portfolios.show', ['project' => $project->public_id])" size="sm">Lihat proyek</flux:button>
                    @can('update', $project)
                        <flux:button wire:click="editProject({{ $project->id }})" size="sm">Edit</flux:button>
                        <flux:button wire:click="deleteProject({{ $project->id }})" wire:confirm="Hapus proyek dan bukti unggahannya? Tautan berbagi juga akan berhenti berfungsi." variant="danger" size="sm">Hapus</flux:button>
                    @endcan
                </div>
                @if ($project->is_published)
                    <flux:input :value="route('portfolios.show', ['project' => $project->public_id])" label="Tautan publik" readonly size="sm" />
                @endif
            </article>
        @empty
            <div class="col-span-full flex flex-col items-start gap-3 rounded-xl border border-dashed border-zinc-300 p-8 dark:border-zinc-700">
                <flux:heading size="lg" level="2">{{ $search !== '' || $publication !== '' ? 'Tidak ada proyek yang cocok' : 'Karya pertama dimulai dari pengalaman Anda' }}</flux:heading>
                <flux:text>{{ $search !== '' || $publication !== '' ? 'Ubah kata pencarian atau pilihan akses untuk melihat proyek lainnya.' : (auth()->user()->isMahasiswa() ? 'Tambahkan proyek praktikum atau magang, jelaskan peran Anda, lalu lampirkan buktinya. Proyek baru bersifat privat.' : 'Proyek akan muncul ketika mahasiswa bimbingan menambahkannya. Penugasan dosen mengikuti kelas praktikum atau penempatan magang.') }}</flux:text>
                @can('create', PortfolioProject::class)<flux:button wire:click="createProject">Tambah proyek</flux:button>@endcan
            </div>
        @endforelse
    </div>
    {{ $this->projects->links() }}
</section>
