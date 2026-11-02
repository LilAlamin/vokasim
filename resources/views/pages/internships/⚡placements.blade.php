<?php

use App\Actions\SaveInternshipPlacement;
use App\Enums\UserRole;
use App\Models\InternshipPlacement;
use App\Models\PartnerCompany;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Penempatan Magang')] class extends Component
{
    use WithPagination;

    #[Locked]
    public ?int $editingId = null;

    public array $form = [];

    public function mount(): void
    {
        Gate::authorize('create', InternshipPlacement::class);
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->form = ['student_id' => '', 'mentor_id' => '', 'lecturer_id' => '', 'partner_company_id' => '', 'partner_unit_id' => '', 'company_name' => '', 'address' => '', 'latitude' => '', 'longitude' => '', 'radius_meters' => 100, 'timezone' => 'Asia/Jakarta', 'starts_on' => now()->toDateString(), 'ends_on' => now()->addMonths(3)->toDateString(), 'target_hours' => 600];
        $this->resetValidation();
    }

    public function edit(int $id): void
    {
        Gate::authorize('create', InternshipPlacement::class);
        $placement = InternshipPlacement::findOrFail($id);
        $this->editingId = $placement->id;
        $this->form = $placement->only(array_keys($this->form));
        $this->form['starts_on'] = $placement->starts_on->toDateString();
        $this->form['ends_on'] = $placement->ends_on->toDateString();
        $this->resetValidation();
    }

    public function save(SaveInternshipPlacement $save): void
    {
        $save->handle(Auth::user(), $this->form, $this->editingId);
        $this->resetForm();
        $this->resetPage();
        unset($this->placements);
        Flux::toast(variant: 'success', text: 'Penempatan magang tersimpan.');
    }

    public function updated(string $property): void
    {
        Gate::authorize('create', InternshipPlacement::class);

        if ($property === 'form.partner_company_id') {
            $this->form['partner_unit_id'] = '';
            $company = $this->partners->firstWhere('id', (int) ($this->form['partner_company_id'] ?? 0));

            if ($company) {
                $this->form['company_name'] = $company->name;
                $this->form['address'] = $company->address;
                $this->form['latitude'] = $company->latitude ?? '';
                $this->form['longitude'] = $company->longitude ?? '';
            }
        }

        if ($property === 'form.partner_unit_id' && ! empty($this->form['partner_unit_id'])) {
            $company = $this->partners->firstWhere('id', (int) ($this->form['partner_company_id'] ?? 0));
            $unit = $company?->units->firstWhere('id', (int) $this->form['partner_unit_id']);

            if ($unit) {
                $this->form = [...$this->form, ...$unit->only(['address', 'latitude', 'longitude', 'radius_meters', 'timezone'])];
            }
        }
    }

    #[Computed]
    public function partners(): \Illuminate\Database\Eloquent\Collection
    {
        Gate::authorize('create', InternshipPlacement::class);

        return PartnerCompany::query()->with(['units' => fn ($query) => $query->where('is_active', true)->orderBy('name')])->orderBy('name')->get();
    }

    #[Computed]
    public function users(): \Illuminate\Database\Eloquent\Collection
    {
        Gate::authorize('create', InternshipPlacement::class);

        return User::query()->orderBy('name')->get(['id', 'name', 'role', 'email']);
    }

    #[Computed]
    public function placements(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        Gate::authorize('create', InternshipPlacement::class);

        return InternshipPlacement::query()->with(['student', 'mentor', 'lecturer', 'unit'])->withCount(['attendances', 'logbooks', 'leaveRequests', 'evaluations', 'projects'])->latest('id')->paginate(10);
    }
}; ?>

<section class="mx-auto flex w-full max-w-6xl flex-col gap-8">
    <header>
        <flux:heading size="xl" level="1">Penempatan Magang</flux:heading>
        <flux:text class="mt-2">Hubungkan mahasiswa dengan pembimbing dan tetapkan area presensi di lokasi DUDI.</flux:text>
    </header>

    <form wire:submit="save" class="rounded-xl border border-zinc-200 p-5 sm:p-6 dark:border-zinc-700">
        <flux:heading size="lg" level="2">{{ $editingId ? 'Ubah penempatan' : 'Tambah penempatan' }}</flux:heading>
        <flux:text class="mt-1">Koordinat harus menunjuk lokasi kerja yang sebenarnya. Radius berlaku untuk presensi datang dan pulang.</flux:text>
        @if ($errors->any())
            <div role="alert" class="mt-4 rounded-lg bg-red-50 p-4 text-sm text-red-800 dark:bg-red-950 dark:text-red-200">
                <ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        <div class="mt-6 grid gap-5 md:grid-cols-3">
            @foreach (['student_id' => ['Mahasiswa', UserRole::Mahasiswa], 'mentor_id' => ['Mentor DUDI', UserRole::MentorDudi], 'lecturer_id' => ['Dosen pembimbing', UserRole::Dosen]] as $field => [$label, $role])
                <flux:select wire:model="form.{{ $field }}" :name="$field" :label="$label" required wire:key="placement-{{ $field }}">
                    <option value="">Pilih {{ strtolower($label) }}</option>
                    @foreach ($this->users->where('role', $role) as $user)
                        <option value="{{ $user->id }}" wire:key="{{ $field }}-{{ $user->id }}">{{ $user->name }} · {{ $user->email }}</option>
                    @endforeach
                </flux:select>
            @endforeach
        </div>
        <div class="mt-5 grid gap-5 md:grid-cols-2">
            <flux:select wire:model.live="form.partner_company_id" name="partner_company_id" label="Mitra terdaftar (opsional)">
                <option value="">Isi perusahaan secara manual</option>
                @foreach ($this->partners as $company)<option wire:key="registered-partner-{{ $company->id }}" value="{{ $company->id }}" @disabled(! $company->is_active)>{{ $company->name }} · kuota {{ $company->internship_quota }}{{ $company->is_active ? '' : ' · Nonaktif' }}</option>@endforeach
            </flux:select>
            <flux:select wire:model.live="form.partner_unit_id" name="partner_unit_id" label="Unit kerja (opsional)">
                <option value="">Tanpa unit / lokasi manual</option>
                @foreach (($this->partners->firstWhere('id', (int) ($form['partner_company_id'] ?? 0))?->units ?? []) as $unit)<option wire:key="registered-unit-{{ $unit->id }}" value="{{ $unit->id }}">{{ $unit->name }} · kuota {{ $unit->internship_quota }}</option>@endforeach
            </flux:select>
        </div>
        <flux:text class="mt-3">Kuota mitra dan unit diperiksa untuk seluruh periode penempatan. Memilih unit menggunakan koordinat, radius, dan zona waktu unit tersebut.</flux:text>
        <div class="mt-5 grid gap-5 md:grid-cols-2">
            <flux:input wire:model="form.company_name" name="company_name" label="Nama perusahaan / mitra DUDI" maxlength="255" required />
            <flux:input wire:model="form.address" name="address" label="Alamat lokasi kerja" maxlength="500" required />
        </div>
        <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <flux:input wire:model="form.latitude" name="latitude" label="Latitude lokasi" type="number" step="any" min="-90" max="90" placeholder="-6.2000000" required />
            <flux:input wire:model="form.longitude" name="longitude" label="Longitude lokasi" type="number" step="any" min="-180" max="180" placeholder="106.8166667" required />
            <flux:input wire:model="form.radius_meters" name="radius_meters" label="Radius (meter)" type="number" min="10" max="1000" required />
            <flux:select wire:model="form.timezone" name="timezone" label="Zona waktu lokasi">
                <option value="Asia/Jakarta">WIB</option><option value="Asia/Makassar">WITA</option><option value="Asia/Jayapura">WIT</option>
            </flux:select>
        </div>
        <div class="mt-5 grid gap-5 sm:grid-cols-3">
            <flux:input wire:model="form.starts_on" name="starts_on" label="Tanggal mulai" type="date" required />
            <flux:input wire:model="form.ends_on" name="ends_on" label="Tanggal selesai" type="date" required />
            <flux:input wire:model="form.target_hours" name="target_hours" label="Target jam magang" type="number" min="1" max="5000" required />
        </div>
        <div class="mt-6 flex flex-wrap gap-3">
            <flux:button type="submit" variant="primary">Simpan penempatan</flux:button>
            @if ($editingId)<flux:button wire:click="resetForm">Batal mengubah</flux:button>@endif
        </div>
    </form>

    <div class="flex flex-col gap-4">
        <flux:heading size="lg" level="2">Daftar penempatan</flux:heading>
        @forelse ($this->placements as $placement)
            <article wire:key="placement-{{ $placement->id }}" class="flex flex-col justify-between gap-4 border-b border-zinc-200 pb-5 sm:flex-row dark:border-zinc-700">
                <div class="min-w-0 space-y-1">
                    <flux:heading class="break-words">{{ $placement->student->name }} · {{ $placement->company_name }}</flux:heading>
                    <flux:text>{{ $placement->starts_on->format('d M Y') }} – {{ $placement->ends_on->format('d M Y') }} · Radius {{ $placement->radius_meters }} m · {{ $placement->target_hours }} jam</flux:text>
                    <flux:text>Mentor: {{ $placement->mentor->name }} · Dosen: {{ $placement->lecturer->name }}</flux:text>
                    @if ($placement->unit)<flux:text>Unit kerja: {{ $placement->unit->name }}</flux:text>@endif
                </div>
                @if ($placement->attendances_count === 0 && $placement->logbooks_count === 0 && $placement->leave_requests_count === 0 && $placement->evaluations_count === 0 && $placement->projects_count === 0)
                    <flux:button wire:click="edit({{ $placement->id }})" size="sm">Ubah penempatan</flux:button>
                @else
                    <flux:text class="shrink-0">Terkunci · sudah ada aktivitas</flux:text>
                @endif
            </article>
        @empty
            <flux:text>Belum ada penempatan. Isi formulir di atas agar mahasiswa dapat mulai presensi dan mengisi logbook.</flux:text>
        @endforelse
        {{ $this->placements->links() }}
    </div>
</section>
