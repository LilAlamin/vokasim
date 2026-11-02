<?php

use App\Actions\AiInterviewClient;
use App\Actions\StartInterviewSession;
use App\Models\InterviewSession;
use App\Enums\InterviewStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Latihan Wawancara')] class extends Component {
    use WithPagination;

    public string $target_position = '';
    public string $difficulty = 'beginner';
    public string $interview_type = 'behavioral';
    public string $interaction_mode = 'text';
    public int $question_count = 5;
    public bool $use_context = false;
    public bool $consent = false;
    public string $search = '';

    public function boot(): void
    {
        Gate::authorize('viewAny', InterviewSession::class);
    }

    public function start(StartInterviewSession $start): void
    {
        $this->resetValidation();
        $session = $start->handle(Auth::user(), $this->only(['target_position', 'difficulty', 'interview_type', 'interaction_mode', 'question_count', 'use_context', 'consent']));
        $this->redirectRoute('interviews.session', ['session' => $session], navigate: true);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function sessions(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return InterviewSession::query()->forUser(Auth::user())->with('student')
            ->when($this->search !== '', fn ($query) => $query->where(function ($search): void {
                $search->where('target_position', 'like', '%'.mb_substr($this->search, 0, 100).'%')
                    ->orWhereHas('student', fn ($student) => $student->where('name', 'like', '%'.mb_substr($this->search, 0, 100).'%'));
            }))->latest('id')->paginate(10);
    }

    #[Computed]
    public function ready(): bool
    {
        return app(AiInterviewClient::class)->ready();
    }
}; ?>

<section class="mx-auto flex w-full max-w-6xl flex-col gap-8">
    <header class="space-y-2">
        <flux:heading size="xl" level="1">{{ auth()->user()->isMahasiswa() ? 'Latihan Wawancara' : 'Wawancara Mahasiswa Bimbingan' }}</flux:heading>
        <flux:text>{{ auth()->user()->isMahasiswa() ? 'Latih jawaban sesuai posisi tujuan, lalu pelajari umpan balik dan contoh perbaikannya.' : 'Tinjau transkrip, rekaman, dan perkembangan latihan mahasiswa yang Anda bimbing.' }}</flux:text>
    </header>
    @if ($errors->any())
        <div role="alert" class="rounded-xl bg-red-50 p-4 text-sm text-red-800 dark:bg-red-950 dark:text-red-200">{{ $errors->first() }}</div>
    @endif
    @if (auth()->user()->isMahasiswa())
        <div class="grid items-start gap-8 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <form wire:submit="start" class="space-y-5">
                <flux:heading size="lg" level="2">Siapkan sesi latihan</flux:heading>
                @if (! $this->ready)
                    <div role="status" class="rounded-xl bg-amber-50 p-4 text-sm text-amber-900 dark:bg-amber-950 dark:text-amber-100">Layanan AI belum diaktifkan. Administrator perlu menghubungkan layanan sebelum sesi baru dapat dimulai. Riwayat latihan tetap dapat dibuka.</div>
                @endif
                <flux:input name="target_position" wire:model="target_position" label="Posisi pekerjaan tujuan" placeholder="Contoh: Network Technician" list="interview-positions" maxlength="120" required />
                <datalist id="interview-positions">@foreach (config('interviews.positions') as $position)<option wire:key="position-{{ $loop->index }}" value="{{ $position }}"></option>@endforeach</datalist>
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:select name="difficulty" wire:model="difficulty" label="Tingkat kesulitan"><option value="beginner">Pemula</option><option value="intermediate">Menengah</option><option value="advanced">Lanjutan</option></flux:select>
                    <flux:select name="interview_type" wire:model="interview_type" label="Fokus wawancara"><option value="behavioral">Perilaku & pengalaman</option><option value="technical">Teknis vokasi</option><option value="scenario">Studi kasus</option></flux:select>
                    <flux:select name="interaction_mode" wire:model="interaction_mode" label="Cara menjawab"><option value="text">Teks</option><option value="voice">Suara & transkrip</option><option value="video">Video & transkrip</option></flux:select>
                    <flux:select name="question_count" wire:model="question_count" label="Jumlah pertanyaan">@foreach (range(5, 10) as $count)<option wire:key="count-{{ $count }}" value="{{ $count }}">{{ $count }} pertanyaan</option>@endforeach</flux:select>
                </div>
                <flux:checkbox name="use_context" wire:model="use_context" label="Sesuaikan dengan pengalaman saya" description="Kirim ringkasan proyek, sertifikat terverifikasi, dan logbook terverifikasi ke layanan AI sebagai konteks pertanyaan." />
                <flux:checkbox name="consent" wire:model="consent" label="Saya menyetujui pemrosesan jawaban oleh layanan AI" description="Jawaban teks dan audio untuk transkripsi dikirim ke penyedia AI. Video disimpan privat di VokaSim dan tidak dikirim untuk penilaian AI." />
                <flux:button type="submit" variant="primary" :disabled="! $this->ready" wire:loading.attr="disabled" wire:target="start">Mulai latihan</flux:button>
            </form>
            <aside class="space-y-4 border-t border-zinc-200 pt-5 lg:border-t-0 lg:pt-0 dark:border-zinc-700">
                <flux:heading level="2">Ruang latihan pribadi</flux:heading>
                <flux:text>Setiap pertanyaan memiliki waktu {{ (int) config('interviews.answer_seconds', 180) }} detik. Pertanyaan berikutnya dibuka saat Anda siap.</flux:text>
                <flux:text>Transkrip dan rekaman hanya dapat dilihat oleh Anda dan dosen pembimbing. Rekaman dapat dihapus, dan dibersihkan otomatis setelah {{ config('interviews.media_retention_days') }} hari.</flux:text>
                <flux:text>Skor AI membantu latihan. Skor ini bukan nilai akademik atau keputusan penerimaan kerja. Suara pewawancara dibuat oleh AI.</flux:text>
                <flux:button :href="route('careers.index')" variant="ghost" wire:navigate>Lihat rekomendasi karier</flux:button>
            </aside>
        </div>
    @endif
    <div class="space-y-4 border-t border-zinc-200 pt-6 dark:border-zinc-700">
        <div class="flex flex-wrap items-end justify-between gap-4"><flux:heading size="lg" level="2">Riwayat latihan</flux:heading><flux:input name="search" wire:model.live.debounce.300ms="search" label="Cari riwayat" placeholder="Posisi atau nama mahasiswa" class="sm:max-w-xs" /></div>
        <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
            @forelse ($this->sessions as $session)
                <a wire:key="session-{{ $session->id }}" href="{{ route('interviews.session', $session) }}" wire:navigate class="flex flex-wrap items-center justify-between gap-4 py-5 hover:bg-zinc-50 focus-visible:outline-2 focus-visible:outline-blue-600 dark:hover:bg-zinc-800">
                    <div class="min-w-0 space-y-1"><p class="break-words font-medium">{{ $session->target_position }}</p><p class="text-sm text-zinc-600 dark:text-zinc-400">{{ auth()->user()->isDosen() ? $session->student->name.' · ' : '' }}{{ $session->created_at->timezone('Asia/Jakarta')->format('d M Y H:i') }} · {{ $session->question_count }} pertanyaan</p></div>
                    <div class="flex items-center gap-4"><flux:badge :color="$session->status->color()">{{ $session->status->label() }}</flux:badge>@if ($session->overall_score !== null)<span class="font-semibold tabular-nums">{{ number_format($session->overall_score, 1) }} / 100</span>@endif<flux:icon.chevron-right class="size-4" /></div>
                </a>
            @empty
                <div class="py-8"><flux:heading level="3">Belum ada sesi pada daftar ini</flux:heading><flux:text class="mt-2">{{ auth()->user()->isMahasiswa() ? 'Mulai latihan pertama dari formulir di atas.' : 'Latihan mahasiswa bimbingan akan muncul setelah mereka memulai sesi.' }}</flux:text></div>
            @endforelse
        </div>
        {{ $this->sessions->links() }}
    </div>
</section>
