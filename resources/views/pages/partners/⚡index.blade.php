<?php

use App\Actions\SavePartnerAgreement;
use App\Actions\SavePartnerCompany;
use App\Actions\SavePartnerUnit;
use App\Models\PartnerCompany;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Title('Kemitraan & Perjanjian')] class extends Component
{
    use WithFileUploads, WithPagination;

    public string $search = '';

    public string $agreementFilter = '';

    #[Locked]
    public ?int $companyId = null;

    #[Locked]
    public ?int $unitId = null;

    #[Locked]
    public ?int $agreementId = null;

    public bool $showCompanyForm = false;

    public bool $showUnitForm = false;

    public bool $showAgreementForm = false;

    public array $companyForm = [];

    public array $unitForm = [];

    public array $agreementForm = [];

    public $document = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', PartnerCompany::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'agreementFilter'], true)) {
            $this->resetPage();
        }
    }

    public function selectCompany(int $id): void
    {
        Gate::authorize('viewAny', PartnerCompany::class);
        $this->companyId = PartnerCompany::query()->findOrFail($id)->id;
        $this->cancelEditor();
        unset($this->selectedCompany);
    }

    public function newCompany(): void
    {
        Gate::authorize('create', PartnerCompany::class);
        $this->cancelEditor();
        $this->companyId = null;
        $this->companyForm = ['name' => '', 'sector' => '', 'address' => '', 'city' => '', 'province' => '', 'latitude' => '', 'longitude' => '', 'contact_name' => '', 'contact_email' => '', 'contact_phone' => '', 'internship_quota' => 0, 'is_active' => true];
        $this->showCompanyForm = true;
        unset($this->selectedCompany);
    }

    public function editCompany(): void
    {
        Gate::authorize('viewAny', PartnerCompany::class);
        abort_unless($this->selectedCompany, 404);
        $this->cancelEditor();
        $this->companyForm = $this->selectedCompany->only(['name', 'sector', 'address', 'city', 'province', 'latitude', 'longitude', 'contact_name', 'contact_email', 'contact_phone', 'internship_quota', 'is_active']);
        $this->showCompanyForm = true;
    }

    public function saveCompany(SavePartnerCompany $save): void
    {
        $company = $save->handle(Auth::user(), $this->companyForm, $this->companyId);
        $this->companyId = $company->id;
        $this->cancelEditor();
        $this->resetPage();
        unset($this->companies, $this->selectedCompany);
        Flux::toast(variant: 'success', text: 'Profil mitra tersimpan.');
    }

    public function newUnit(): void
    {
        Gate::authorize('viewAny', PartnerCompany::class);
        abort_unless($this->selectedCompany, 404);
        $this->cancelEditor();
        $company = $this->selectedCompany;
        $this->unitForm = ['name' => '', 'address' => $company->address, 'latitude' => $company->latitude ?? '', 'longitude' => $company->longitude ?? '', 'radius_meters' => 100, 'timezone' => 'Asia/Jakarta', 'internship_quota' => 0, 'is_active' => true];
        $this->showUnitForm = true;
    }

    public function editUnit(int $id): void
    {
        Gate::authorize('viewAny', PartnerCompany::class);
        abort_unless($this->selectedCompany, 404);
        $unit = $this->selectedCompany->units()->findOrFail($id);
        $this->cancelEditor();
        $this->unitId = $unit->id;
        $this->unitForm = $unit->only(['name', 'address', 'latitude', 'longitude', 'radius_meters', 'timezone', 'internship_quota', 'is_active']);
        $this->showUnitForm = true;
    }

    public function saveUnit(SavePartnerUnit $save): void
    {
        abort_unless($this->companyId, 404);
        $save->handle(Auth::user(), $this->companyId, $this->unitForm, $this->unitId);
        $this->cancelEditor();
        unset($this->companies, $this->selectedCompany);
        Flux::toast(variant: 'success', text: 'Unit kerja dan alokasi kuota tersimpan.');
    }

    public function newAgreement(): void
    {
        Gate::authorize('viewAny', PartnerCompany::class);
        abort_unless($this->selectedCompany, 404);
        $this->cancelEditor();
        $this->agreementForm = ['reference_number' => '', 'kind' => 'mou', 'signed_on' => today()->toDateString(), 'starts_on' => today()->toDateString(), 'expires_on' => today()->addYear()->toDateString(), 'notes' => '', 'is_active' => true];
        $this->showAgreementForm = true;
    }

    public function editAgreement(int $id): void
    {
        Gate::authorize('viewAny', PartnerCompany::class);
        abort_unless($this->selectedCompany, 404);
        $agreement = $this->selectedCompany->agreements()->findOrFail($id);
        $this->cancelEditor();
        $this->agreementId = $agreement->id;
        $this->agreementForm = $agreement->only(['reference_number', 'kind', 'notes', 'is_active']);
        foreach (['signed_on', 'starts_on', 'expires_on'] as $field) {
            $this->agreementForm[$field] = $agreement->$field->toDateString();
        }
        $this->showAgreementForm = true;
    }

    public function saveAgreement(SavePartnerAgreement $save): void
    {
        abort_unless($this->companyId, 404);
        $save->handle(Auth::user(), $this->companyId, $this->agreementForm, $this->document, $this->agreementId);
        $this->cancelEditor();
        unset($this->companies, $this->selectedCompany);
        Flux::toast(variant: 'success', text: 'Dokumen kerja sama tersimpan secara privat.');
    }

    public function cancelEditor(): void
    {
        $this->showCompanyForm = $this->showUnitForm = $this->showAgreementForm = false;
        $this->unitId = $this->agreementId = null;
        $this->reset('document');
        $this->resetValidation();
    }

    #[Computed]
    public function companies(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        Gate::authorize('viewAny', PartnerCompany::class);
        Validator::make(['search' => $this->search, 'agreement_filter' => $this->agreementFilter], [
            'search' => ['string', 'max:100'],
            'agreement_filter' => ['nullable', \Illuminate\Validation\Rule::in(['expiring', 'expired'])],
        ])->validate();

        return PartnerCompany::query()
            ->when($this->search !== '', fn ($query) => $query->where(fn ($query) => $query->where('name', 'like', '%'.$this->search.'%')->orWhere('city', 'like', '%'.$this->search.'%')))
            ->when($this->agreementFilter === 'expiring', fn ($query) => $query->whereHas('agreements', fn ($query) => $query->expiringSoon()))
            ->when($this->agreementFilter === 'expired', fn ($query) => $query->whereHas('agreements', fn ($query) => $query->where('is_active', true)->whereDate('expires_on', '<', today())))
            ->withCount(['agreements as expiring_count' => fn ($query) => $query->expiringSoon()])
            ->orderBy('name')->orderBy('id')->paginate(8);
    }

    #[Computed]
    public function selectedCompany(): ?PartnerCompany
    {
        Gate::authorize('viewAny', PartnerCompany::class);

        return $this->companyId ? PartnerCompany::query()
            ->with(['units' => fn ($query) => $query->orderBy('name'), 'agreements' => fn ($query) => $query->orderBy('expires_on')])
            ->withCount(['placements as current_placements' => fn ($query) => $query->whereDate('starts_on', '<=', today())->whereDate('ends_on', '>=', today())])
            ->findOrFail($this->companyId) : null;
    }
}; ?>

<section class="mx-auto flex w-full max-w-6xl flex-col gap-7">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div><flux:heading size="xl" level="1">Kemitraan & Perjanjian</flux:heading><flux:text class="mt-2">Kelola profil DUDI, lokasi unit kerja, kuota magang, dan masa berlaku MoU/MOA.</flux:text></div>
        <flux:button wire:click="newCompany" variant="primary">Tambah mitra</flux:button>
    </header>
    @if ($errors->any())<div role="alert" class="rounded-lg bg-red-50 p-4 text-sm text-red-800 dark:bg-red-950 dark:text-red-200"><ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li wire:key="partner-error-{{ $loop->index }}">{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="grid items-start gap-7 lg:grid-cols-[18rem_minmax(0,1fr)]">
        <aside class="flex min-w-0 flex-col gap-4">
            <flux:input wire:model.live.debounce.300ms="search" label="Cari mitra atau kota" maxlength="100" />
            <flux:select wire:model.live="agreementFilter" label="Masa berlaku perjanjian"><option value="">Semua mitra</option><option value="expiring">Berakhir dalam 30 hari</option><option value="expired">Memiliki perjanjian kedaluwarsa</option></flux:select>
            <div class="flex flex-col gap-2">
                @forelse ($this->companies as $company)
                    <button type="button" wire:key="company-{{ $company->id }}" wire:click="selectCompany({{ $company->id }})" aria-pressed="{{ $companyId === $company->id ? 'true' : 'false' }}" @class(['flex flex-col gap-2 rounded-lg border p-4 text-left transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600', 'border-blue-600 bg-blue-50 dark:bg-blue-950' => $companyId === $company->id, 'border-zinc-200 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800' => $companyId !== $company->id])>
                        <span class="break-words font-medium">{{ $company->name }}</span>
                        <span class="text-sm text-zinc-600 dark:text-zinc-300">{{ $company->city }} · {{ $company->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                        @if ($company->expiring_count > 0)<span class="text-sm font-medium text-amber-800 dark:text-amber-300">{{ $company->expiring_count }} perjanjian segera berakhir</span>@endif
                    </button>
                @empty
                    <flux:text>Belum ada mitra yang cocok. Tambahkan profil mitra baru atau ubah filter pencarian.</flux:text>
                @endforelse
            </div>
            {{ $this->companies->links() }}
        </aside>

        <div class="flex min-w-0 flex-col gap-7">
            @if ($showCompanyForm)
                <form wire:submit="saveCompany" class="flex flex-col gap-5 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                    <flux:heading size="lg" level="2">{{ $companyId ? 'Ubah profil mitra' : 'Profil mitra baru' }}</flux:heading>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="companyForm.name" name="name" label="Nama perusahaan" maxlength="255" required />
                        <flux:input wire:model="companyForm.sector" name="sector" label="Bidang industri" maxlength="255" required />
                        <flux:input wire:model="companyForm.city" name="city" label="Kota / kabupaten" maxlength="100" required />
                        <flux:input wire:model="companyForm.province" name="province" label="Provinsi" maxlength="100" required />
                    </div>
                    <flux:textarea wire:model="companyForm.address" name="address" label="Alamat perusahaan" rows="2" maxlength="500" required />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="companyForm.latitude" name="latitude" label="Latitude (opsional)" type="number" step="any" min="-90" max="90" />
                        <flux:input wire:model="companyForm.longitude" name="longitude" label="Longitude (opsional)" type="number" step="any" min="-180" max="180" />
                        <flux:input wire:model="companyForm.contact_name" name="contact_name" label="Nama narahubung" maxlength="255" />
                        <flux:input wire:model="companyForm.contact_email" name="contact_email" label="Email narahubung" type="email" maxlength="255" />
                        <flux:input wire:model="companyForm.contact_phone" name="contact_phone" label="Telepon narahubung" maxlength="40" />
                        <flux:input wire:model="companyForm.internship_quota" name="internship_quota" label="Kuota mahasiswa bersamaan" type="number" min="0" max="10000" required />
                    </div>
                    <flux:checkbox wire:model="companyForm.is_active" name="is_active" label="Aktif menerima penempatan baru" />
                    <flux:text>Kuota berlaku pada penempatan dengan tanggal yang bertumpang tindih. Jumlah alokasi unit aktif tidak boleh melebihi kuota perusahaan.</flux:text>
                    <div class="flex flex-wrap gap-3"><flux:button type="submit" variant="primary" wire:loading.attr="disabled">Simpan profil</flux:button><flux:button type="button" wire:click="cancelEditor">Batal</flux:button></div>
                </form>
            @elseif ($this->selectedCompany)
                <div class="flex flex-col gap-4 border-b border-zinc-200 pb-6 dark:border-zinc-700">
                    <div class="flex flex-wrap items-start justify-between gap-3"><flux:heading size="lg" level="2">{{ $this->selectedCompany->name }}</flux:heading><flux:button wire:click="editCompany" size="sm">Ubah profil</flux:button></div>
                    <flux:text>{{ $this->selectedCompany->sector }} · {{ $this->selectedCompany->city }}, {{ $this->selectedCompany->province }}</flux:text>
                    <p class="break-words text-sm">{{ $this->selectedCompany->address }}</p>
                    <dl class="grid gap-4 sm:grid-cols-2">
                        <div><dt class="text-sm text-zinc-600 dark:text-zinc-300">Kuota / penempatan hari ini</dt><dd class="mt-1 font-medium tabular-nums">{{ $this->selectedCompany->internship_quota }} / {{ $this->selectedCompany->current_placements }} mahasiswa</dd></div>
                        <div><dt class="text-sm text-zinc-600 dark:text-zinc-300">Penerimaan</dt><dd class="mt-1 font-medium">{{ $this->selectedCompany->is_active ? 'Aktif' : 'Nonaktif' }}</dd></div>
                        <div><dt class="text-sm text-zinc-600 dark:text-zinc-300">Narahubung</dt><dd class="mt-1 break-words text-sm">{{ $this->selectedCompany->contact_name ?: 'Belum diisi' }}<br>{{ $this->selectedCompany->contact_email }}<br>{{ $this->selectedCompany->contact_phone }}</dd></div>
                        <div><dt class="text-sm text-zinc-600 dark:text-zinc-300">Koordinat perusahaan</dt><dd class="mt-1 text-sm tabular-nums">{{ $this->selectedCompany->latitude !== null ? $this->selectedCompany->latitude.', '.$this->selectedCompany->longitude : 'Belum diisi' }}</dd></div>
                    </dl>
                    <div><flux:button :href="route('internships.placements')" wire:navigate size="sm">Atur penempatan mahasiswa</flux:button></div>
                </div>

                <section class="flex flex-col gap-4">
                    <div class="flex flex-wrap items-center justify-between gap-3"><flux:heading size="lg" level="2">Unit kerja & alokasi</flux:heading><flux:button wire:click="newUnit" size="sm">Tambah unit</flux:button></div>
                    @if ($showUnitForm)
                        <form wire:submit="saveUnit" class="flex flex-col gap-4 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                            <flux:heading level="3">{{ $unitId ? 'Ubah unit kerja' : 'Unit kerja baru' }}</flux:heading>
                            <flux:input wire:model="unitForm.name" name="name" label="Nama unit" maxlength="255" required />
                            <flux:input wire:model="unitForm.address" name="address" label="Alamat lokasi kerja" maxlength="500" required />
                            <div class="grid gap-4 sm:grid-cols-2">
                                <flux:input wire:model="unitForm.latitude" name="latitude" label="Latitude" type="number" step="any" min="-90" max="90" required />
                                <flux:input wire:model="unitForm.longitude" name="longitude" label="Longitude" type="number" step="any" min="-180" max="180" required />
                                <flux:input wire:model="unitForm.radius_meters" name="radius_meters" label="Radius presensi (meter)" type="number" min="10" max="1000" required />
                                <flux:select wire:model="unitForm.timezone" name="timezone" label="Zona waktu"><option value="Asia/Jakarta">WIB</option><option value="Asia/Makassar">WITA</option><option value="Asia/Jayapura">WIT</option></flux:select>
                                <flux:input wire:model="unitForm.internship_quota" name="internship_quota" label="Kuota unit" type="number" min="0" max="10000" required />
                            </div>
                            <flux:checkbox wire:model="unitForm.is_active" name="is_active" label="Unit aktif" />
                            <flux:text>Perubahan lokasi diterapkan pada penempatan baru. Lokasi yang sudah tercatat pada penempatan tetap menjadi acuan presensinya.</flux:text>
                            <div class="flex flex-wrap gap-3"><flux:button type="submit" variant="primary" wire:loading.attr="disabled">Simpan unit</flux:button><flux:button type="button" wire:click="cancelEditor">Batal</flux:button></div>
                        </form>
                    @endif
                    @forelse ($this->selectedCompany->units as $unit)
                        <article wire:key="partner-unit-{{ $unit->id }}" class="flex flex-wrap items-start justify-between gap-3 border-b border-zinc-200 pb-4 dark:border-zinc-700">
                            <div><flux:heading level="3">{{ $unit->name }}{{ $unit->is_active ? '' : ' · Nonaktif' }}</flux:heading><flux:text class="mt-1">Kuota {{ $unit->internship_quota }} · Radius {{ $unit->radius_meters }} m · {{ $unit->timezone }}</flux:text><p class="mt-1 break-words text-sm text-zinc-600 dark:text-zinc-300">{{ $unit->address }}</p></div>
                            <flux:button wire:click="editUnit({{ $unit->id }})" size="sm">Ubah unit</flux:button>
                        </article>
                    @empty
                        <flux:text>Belum ada unit kerja. Tambahkan lokasi untuk menggunakan koordinat dan kuotanya saat membuat penempatan.</flux:text>
                    @endforelse
                </section>

                <section class="flex flex-col gap-4">
                    <div class="flex flex-wrap items-center justify-between gap-3"><flux:heading size="lg" level="2">Dokumen MoU / MOA</flux:heading><flux:button wire:click="newAgreement" size="sm">Tambah perjanjian</flux:button></div>
                    @if ($showAgreementForm)
                        <form wire:submit="saveAgreement" class="flex flex-col gap-4 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                            <flux:heading level="3">{{ $agreementId ? 'Ubah perjanjian' : 'Perjanjian baru' }}</flux:heading>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <flux:input wire:model="agreementForm.reference_number" name="reference_number" label="Nomor perjanjian" maxlength="150" required />
                                <flux:select wire:model="agreementForm.kind" name="kind" label="Jenis dokumen"><option value="mou">MoU</option><option value="moa">MOA</option></flux:select>
                                <flux:input wire:model="agreementForm.signed_on" name="signed_on" label="Tanggal ditandatangani" type="date" required />
                                <flux:input wire:model="agreementForm.starts_on" name="starts_on" label="Mulai berlaku" type="date" required />
                                <flux:input wire:model="agreementForm.expires_on" name="expires_on" label="Berakhir pada" type="date" required />
                            </div>
                            <flux:input type="file" wire:model="document" label="Dokumen perjanjian" description="PDF maksimal 10 MB. Saat mengubah, kosongkan untuk mempertahankan dokumen yang ada." accept=".pdf" />
                            <span wire:loading wire:target="document" role="status" class="text-sm text-zinc-600 dark:text-zinc-300">Mengunggah dokumen…</span>
                            <flux:textarea wire:model="agreementForm.notes" name="notes" label="Catatan kerja sama" rows="3" maxlength="3000" />
                            <flux:checkbox wire:model="agreementForm.is_active" name="is_active" label="Perjanjian belum dihentikan" />
                            <div class="flex flex-wrap gap-3"><flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="saveAgreement,document">Simpan perjanjian</flux:button><flux:button type="button" wire:click="cancelEditor">Batal</flux:button></div>
                        </form>
                    @endif
                    @forelse ($this->selectedCompany->agreements as $agreement)
                        <article wire:key="partner-agreement-{{ $agreement->id }}" class="flex flex-col gap-3 border-b border-zinc-200 pb-5 dark:border-zinc-700">
                            <div class="flex flex-wrap items-start justify-between gap-3"><flux:heading level="3">{{ strtoupper($agreement->kind) }} · {{ $agreement->reference_number }}</flux:heading><flux:badge :color="in_array($agreement->statusLabel(), ['Kedaluwarsa', 'Berakhir ≤ 30 hari']) ? 'amber' : 'zinc'">{{ $agreement->statusLabel() }}</flux:badge></div>
                            <flux:text>{{ $agreement->starts_on->format('d M Y') }} – {{ $agreement->expires_on->format('d M Y') }}</flux:text>
                            @if ($agreement->notes)<p class="whitespace-pre-wrap break-words text-sm">{{ $agreement->notes }}</p>@endif
                            <div class="flex flex-wrap gap-3">@if ($agreement->document_path)<flux:button :href="route('partners.agreements.download', $agreement)" size="sm">Unduh PDF privat</flux:button>@else<flux:text>Dokumen belum tersedia.</flux:text>@endif<flux:button wire:click="editAgreement({{ $agreement->id }})" size="sm">Ubah perjanjian</flux:button></div>
                        </article>
                    @empty
                        <flux:text>Belum ada perjanjian. Unggah MoU/MOA agar masa berlaku dapat dipantau dan pengingat dapat ditampilkan.</flux:text>
                    @endforelse
                </section>
            @else
                <div class="border-y border-dashed border-zinc-300 py-10 dark:border-zinc-700"><flux:heading size="lg" level="2">Pilih mitra untuk dikelola</flux:heading><flux:text class="mt-2">Profil, unit kerja, kuota, dan dokumen kerja sama akan tampil di sini.</flux:text></div>
            @endif
        </div>
    </div>
</section>
