<?php

use App\Actions\ReviewCertificate;
use App\Actions\SaveCertificate;
use App\Enums\CertificateStatus;
use App\Models\Certificate;
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

new #[Title('Sertifikat Digital')] class extends Component
{
    use WithFileUploads, WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    #[Locked]
    public ?int $selectedId = null;

    #[Locked]
    public ?int $editingId = null;

    public bool $showEditor = false;

    public string $title = '';

    public string $issuer = '';

    public string $credentialNumber = '';

    public string $skills = '';

    public string $issuedOn = '';

    public string $expiresOn = '';

    public bool $publicConsent = false;

    public string $feedback = '';

    public $file = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', Certificate::class);

        if (! Auth::user()->isMahasiswa() && ! request()->query->has('status')) {
            $this->status = CertificateStatus::Pending->value;
        }

        if (request()->integer('certificate') > 0) {
            $this->selectCertificate(request()->integer('certificate'));
        }
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function selectCertificate(int $id): void
    {
        $certificate = Certificate::query()->forUser(Auth::user())->findOrFail($id);
        Gate::authorize('view', $certificate);
        $this->selectedId = $certificate->id;
        $this->feedback = '';
        $this->resetValidation();
        unset($this->selected);
    }

    public function createCertificate(): void
    {
        Gate::authorize('create', Certificate::class);
        $this->cancelEditor();
        $this->showEditor = true;
    }

    public function editCertificate(int $id): void
    {
        $certificate = Certificate::query()->forUser(Auth::user())->findOrFail($id);
        Gate::authorize('update', $certificate);
        $this->cancelEditor();
        $this->editingId = $certificate->id;
        $this->title = $certificate->title;
        $this->issuer = $certificate->issuer;
        $this->credentialNumber = $certificate->credential_number ?? '';
        $this->skills = implode(', ', $certificate->skills);
        $this->issuedOn = $certificate->issued_on->toDateString();
        $this->expiresOn = $certificate->expires_on?->toDateString() ?? '';
        $this->showEditor = true;
    }

    public function cancelEditor(): void
    {
        $this->reset('editingId', 'showEditor', 'title', 'issuer', 'credentialNumber', 'skills', 'issuedOn', 'expiresOn', 'publicConsent', 'file');
        $this->resetValidation();
    }

    public function saveCertificate(SaveCertificate $save): void
    {
        $certificate = $save->handle(Auth::user(), $this->editingId, [
            'title' => $this->title, 'issuer' => $this->issuer, 'credential_number' => $this->credentialNumber,
            'skills' => explode(',', $this->skills), 'issued_on' => $this->issuedOn,
            'expires_on' => $this->expiresOn, 'public_consent' => $this->publicConsent,
        ], $this->file);

        if ($this->file instanceof TemporaryUploadedFile) {
            $this->file->delete();
        }

        $this->cancelEditor();
        $this->selectedId = $certificate->id;
        $this->status = '';
        $this->resetPage();
        unset($this->certificates, $this->selected);
        Flux::toast(variant: 'success', text: 'Sertifikat diajukan untuk verifikasi.');
    }

    public function deleteCertificate(int $id, SaveCertificate $save): void
    {
        $save->delete(Auth::user(), $id);

        if ($this->selectedId === $id) {
            $this->selectedId = null;
        }

        if ($this->editingId === $id) {
            $this->cancelEditor();
        }

        $this->resetPage();
        unset($this->certificates, $this->selected);
        Flux::toast(variant: 'success', text: 'Pengajuan sertifikat dan berkasnya dihapus.');
    }

    public function review(string $decision, ReviewCertificate $review): void
    {
        abort_unless($this->selectedId !== null, 404);
        $review->handle(Auth::user(), $this->selectedId, $decision, $this->feedback);
        $this->feedback = '';
        $this->resetValidation();
        unset($this->certificates, $this->selected);
        Flux::toast(variant: 'success', text: match ($decision) {
            'verify' => 'Sertifikat terverifikasi. QR verifikasi siap dibagikan.',
            'reject' => 'Pengajuan dikembalikan dengan catatan perbaikan.',
            'revoke' => 'Sertifikat dicabut. Halaman verifikasi menampilkan status terbaru.',
        });
    }

    #[Computed]
    public function certificates(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        Gate::authorize('viewAny', Certificate::class);
        $query = Certificate::query()->forUser(Auth::user())->with('student:id,name');

        if (CertificateStatus::tryFrom($this->status) !== null) {
            $query->where('status', $this->status);
        }

        if ($this->search !== '') {
            $query->where('title', 'like', '%'.Str::limit($this->search, 100, '').'%');
        }

        return $query->orderByDesc('created_at')->orderByDesc('id')->paginate(10);
    }

    #[Computed]
    public function selected(): ?Certificate
    {
        Gate::authorize('viewAny', Certificate::class);

        return $this->selectedId === null ? null : Certificate::query()->forUser(Auth::user())
            ->with(['student:id,name', 'reviews.reviewer:id,name'])->findOrFail($this->selectedId);
    }
}; ?>

<section class="mx-auto flex w-full max-w-6xl flex-col gap-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div class="max-w-2xl">
            <flux:heading size="xl" level="1">{{ auth()->user()->isMahasiswa() ? 'Sertifikat Digital' : 'Verifikasi Sertifikat' }}</flux:heading>
            <flux:text class="mt-2">{{ auth()->user()->isMahasiswa() ? 'Simpan sertifikat kompetensi, ajukan pemeriksaan, dan bagikan statusnya melalui QR verifikasi.' : 'Cocokkan identitas, penerbit, masa berlaku, dan kompetensi dengan dokumen sebelum memberikan keputusan.' }}</flux:text>
        </div>
        <div class="flex flex-wrap gap-2">
            <flux:button :href="route('portfolios.index')" wire:navigate>Portofolio</flux:button>
            @can('create', Certificate::class)<flux:button wire:click="createCertificate" variant="primary">Tambah sertifikat</flux:button>@endcan
        </div>
    </header>

    @if ($showEditor)
        <form wire:submit="saveCertificate" class="flex flex-col gap-5 rounded-xl border border-zinc-200 p-5 sm:p-6 dark:border-zinc-700">
            <flux:heading size="lg" level="2">{{ $editingId ? 'Perbarui pengajuan' : 'Ajukan sertifikat' }}</flux:heading>
            <flux:text>Pastikan sertifikat diterbitkan untuk {{ auth()->user()->name }}. Berkas disimpan terenkripsi dan hanya dapat diunduh oleh Anda, dosen pembimbing, dan administrator.</flux:text>
            @if ($errors->any())
                <div role="alert" class="rounded-lg bg-red-50 p-4 text-sm text-red-800 dark:bg-red-950 dark:text-red-200"><ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li wire:key="certificate-form-error-{{ $loop->index }}">{{ $error }}</li>@endforeach</ul></div>
            @endif
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="title" label="Nama sertifikat" maxlength="160" required />
                <flux:input wire:model="issuer" label="Lembaga penerbit" maxlength="160" required />
                <div class="sm:col-span-2"><flux:input wire:model="credentialNumber" label="Nomor sertifikat (opsional)" description="Nomor ini hanya ditampilkan kepada Anda dan pemeriksa dokumen." maxlength="160" /><flux:error name="credential_number" /></div>
                <div><flux:input wire:model="issuedOn" label="Tanggal terbit" type="date" required /><flux:error name="issued_on" /></div>
                <div><flux:input wire:model="expiresOn" label="Berlaku sampai (opsional)" type="date" /><flux:error name="expires_on" /></div>
            </div>
            <flux:input wire:model="skills" label="Kompetensi pada sertifikat" description="Pisahkan dengan koma, maksimal 12. Contoh: PHP, SQL, Git. Pemeriksa akan mencocokkannya dengan dokumen." required />
            <flux:input wire:model="file" type="file" :label="$editingId ? 'Ganti berkas sertifikat (opsional)' : 'Berkas sertifikat'" description="PDF, JPG, atau PNG, maksimal 10 MB." accept="application/pdf,image/jpeg,image/png" :required="! $editingId" />
            <div>
                <flux:checkbox wire:model="publicConsent" label="Saya menyetujui informasi pada halaman verifikasi" description="Setelah disetujui, nama pemegang, nama sertifikat, penerbit, tanggal, dan status dapat dilihat melalui tautan unik. Berkas asli, nomor sertifikat, dan catatan pemeriksaan tetap privat." />
                <flux:error name="public_consent" />
            </div>
            <div class="flex flex-wrap gap-3">
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="saveCertificate,file">Ajukan verifikasi</flux:button>
                <flux:button wire:click="cancelEditor" wire:loading.attr="disabled" wire:target="saveCertificate">Batal</flux:button>
            </div>
            <p wire:loading wire:target="file,saveCertificate" role="status" class="text-sm text-zinc-600 dark:text-zinc-300">Mengunggah dan menyimpan sertifikat…</p>
        </form>
    @endif

    <div class="grid items-start gap-6 lg:grid-cols-2">
        <section class="flex min-w-0 flex-col gap-4" aria-label="Daftar sertifikat">
            <div class="grid gap-3 sm:grid-cols-2">
                <flux:input wire:model.live.debounce.300ms="search" label="Cari sertifikat" type="search" />
                <flux:select wire:model.live="status" label="Status">
                    <option value="">Semua status</option>
                    @foreach (CertificateStatus::cases() as $option)<option wire:key="certificate-status-{{ $option->value }}" value="{{ $option->value }}">{{ $option->label() }}</option>@endforeach
                </flux:select>
            </div>
            @forelse ($this->certificates as $certificate)
                <button type="button" wire:key="certificate-{{ $certificate->id }}" wire:click="selectCertificate({{ $certificate->id }})" aria-pressed="{{ $selectedId === $certificate->id ? 'true' : 'false' }}" @class(['flex min-w-0 flex-col gap-3 rounded-xl border p-5 text-left transition-colors focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-blue-600', 'border-blue-500 bg-blue-50 dark:bg-blue-950' => $selectedId === $certificate->id, 'border-zinc-200 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900' => $selectedId !== $certificate->id])>
                    <span class="flex w-full flex-wrap items-start justify-between gap-2"><span class="min-w-0 flex-1 break-words font-semibold">{{ $certificate->title }}</span><flux:badge :color="$certificate->isExpired() && $certificate->status === CertificateStatus::Verified ? 'red' : $certificate->status->color()" size="sm">{{ $certificate->publicStatus() }}</flux:badge></span>
                    <span @class(['break-words text-sm', 'text-blue-800 dark:text-blue-200' => $selectedId === $certificate->id, 'text-zinc-600 dark:text-zinc-300' => $selectedId !== $certificate->id])>{{ $certificate->issuer }}@unless (auth()->user()->isMahasiswa()) · {{ $certificate->student->name }}@endunless</span>
                    <span class="text-sm">Terbit {{ $certificate->issued_on->format('d M Y') }}</span>
                </button>
            @empty
                <div class="rounded-xl border border-dashed border-zinc-300 p-6 dark:border-zinc-700">
                    <flux:heading level="2">Belum ada sertifikat pada daftar ini</flux:heading>
                    <flux:text class="mt-2">{{ auth()->user()->isMahasiswa() ? 'Tambahkan sertifikat kompetensi pertama Anda, atau ubah filter untuk melihat pengajuan lain.' : 'Pengajuan dari mahasiswa bimbingan akan muncul di sini. Pilih semua status untuk melihat riwayat.' }}</flux:text>
                </div>
            @endforelse
            {{ $this->certificates->links() }}
        </section>

        <section class="flex min-w-0 flex-col gap-5 rounded-xl border border-zinc-200 p-5 sm:p-6 dark:border-zinc-700" aria-label="Detail sertifikat">
            @if ($this->selected)
                <div>
                    <flux:heading size="lg" level="2" class="break-words">{{ $this->selected->title }}</flux:heading>
                    <flux:text class="mt-2">{{ $this->selected->holder_name }} · {{ $this->selected->issuer }}</flux:text>
                </div>
                <dl class="grid gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-zinc-600 dark:text-zinc-400">Status</dt><dd class="mt-1 font-medium">{{ $this->selected->publicStatus() }}</dd></div>
                    <div><dt class="text-zinc-600 dark:text-zinc-400">Nomor sertifikat</dt><dd class="mt-1 break-words">{{ $this->selected->credential_number ?: 'Tidak dicantumkan' }}</dd></div>
                    <div><dt class="text-zinc-600 dark:text-zinc-400">Tanggal terbit</dt><dd class="mt-1">{{ $this->selected->issued_on->format('d M Y') }}</dd></div>
                    <div><dt class="text-zinc-600 dark:text-zinc-400">Berlaku sampai</dt><dd class="mt-1">{{ $this->selected->expires_on?->format('d M Y') ?? 'Tidak dicantumkan' }}</dd></div>
                </dl>
                <div class="flex flex-wrap gap-2">@foreach ($this->selected->skills as $skill)<flux:badge wire:key="certificate-skill-{{ $this->selected->id }}-{{ $loop->index }}" size="sm">{{ $skill }}</flux:badge>@endforeach</div>
                <div class="flex flex-wrap gap-2">
                    <flux:button :href="route('certificates.file', $this->selected)">Unduh berkas privat</flux:button>
                    @can('update', $this->selected)
                        <flux:button wire:click="editCertificate({{ $this->selected->id }})">Edit pengajuan</flux:button>
                        <flux:button wire:click="deleteCertificate({{ $this->selected->id }})" wire:confirm="Hapus pengajuan sertifikat beserta berkas dan riwayatnya?" variant="danger">Hapus</flux:button>
                    @endcan
                </div>
                @if ($this->selected->isPubliclyVerifiable())
                    <div class="flex flex-col items-start gap-4 border-t border-zinc-200 pt-5 dark:border-zinc-700">
                        <flux:heading level="3">QR verifikasi</flux:heading>
                        <img src="{{ route('certificates.qr', $this->selected) }}" alt="QR menuju halaman status sertifikat {{ $this->selected->title }}" width="240" height="240" class="size-48 bg-white" />
                        <flux:input :value="route('certificates.verify', ['certificate' => $this->selected->verification_token])" label="Tautan verifikasi publik" readonly />
                        <flux:button :href="route('certificates.verify', ['certificate' => $this->selected->verification_token])" target="_blank" rel="noopener">Buka halaman verifikasi</flux:button>
                    </div>
                @else
                    <flux:text>QR dan tautan verifikasi tersedia setelah pembimbing atau administrator menyetujui dokumen ini.</flux:text>
                @endif

                @canany(['review', 'revoke'], $this->selected)
                    <form wire:submit="review('verify')" class="flex flex-col gap-4 border-t border-zinc-200 pt-5 dark:border-zinc-700">
                        <flux:heading level="3">Keputusan pemeriksa</flux:heading>
                        @if ($errors->any())<div role="alert" class="text-sm text-red-700 dark:text-red-300"><ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li wire:key="certificate-review-error-{{ $loop->index }}">{{ $error }}</li>@endforeach</ul></div>@endif
                        <flux:textarea wire:model="feedback" label="Catatan pemeriksaan" description="Wajib untuk perbaikan atau pencabutan. Catatan dapat dilihat mahasiswa dan pemeriksa yang berwenang." rows="3" maxlength="2000" />
                        <div class="flex flex-wrap gap-3">
                            @can('review', $this->selected)
                                <flux:button type="submit" variant="primary" wire:confirm="Konfirmasi bahwa pemegang, penerbit, tanggal, dan kompetensi cocok dengan dokumen yang Anda periksa. Lanjutkan verifikasi?" wire:loading.attr="disabled" wire:target="review">Verifikasi sertifikat</flux:button>
                                <flux:button wire:click="review('reject')" wire:loading.attr="disabled" wire:target="review">Minta perbaikan</flux:button>
                            @endcan
                            @can('revoke', $this->selected)
                                <flux:button wire:click="review('revoke')" variant="danger" wire:confirm="Cabut sertifikat ini? Tautan QR akan tetap tersedia dengan status Dicabut dan keputusan ini tidak dapat dibatalkan." wire:loading.attr="disabled" wire:target="review">Cabut sertifikat</flux:button>
                            @endcan
                        </div>
                        <p wire:loading wire:target="review" role="status" class="text-sm text-zinc-600 dark:text-zinc-300">Menyimpan keputusan pemeriksaan…</p>
                    </form>
                @endcanany

                <div class="flex flex-col gap-4 border-t border-zinc-200 pt-5 dark:border-zinc-700">
                    <flux:heading level="3">Riwayat pengajuan</flux:heading>
                    @forelse ($this->selected->reviews as $review)
                        <div wire:key="certificate-review-{{ $review->id }}" class="flex flex-col gap-1 text-sm">
                            <p class="font-medium">{{ $review->to_status->label() }} · {{ $review->reviewer?->name ?? 'Akun pemeriksa tidak tersedia' }}</p>
                            <p class="text-zinc-600 dark:text-zinc-400">{{ $review->created_at->format('d M Y H:i') }}</p>
                            @if ($review->feedback)<p class="whitespace-pre-line break-words leading-relaxed">{{ $review->feedback }}</p>@endif
                        </div>
                    @empty
                        <flux:text>Belum ada keputusan pemeriksaan.</flux:text>
                    @endforelse
                </div>
            @else
                <flux:heading size="lg" level="2">Pilih sertifikat untuk melihat detail</flux:heading>
                <flux:text>Periksa berkas, status, kompetensi, dan riwayat keputusan di sini. Sertifikat yang sudah diverifikasi dikunci untuk menjaga keabsahan tautan QR.</flux:text>
            @endif
        </section>
    </div>
</section>
