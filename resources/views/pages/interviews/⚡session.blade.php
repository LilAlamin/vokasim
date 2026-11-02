<?php

use App\Actions\ManageInterviewSession;
use App\Actions\SubmitInterviewAnswer;
use App\Enums\InterviewStatus;
use App\Models\InterviewSession;
use App\Models\InterviewQuestion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Sesi Wawancara')] class extends Component {
    use WithFileUploads;

    #[Locked]
    public int $sessionId;
    #[Locked]
    public ?int $draftQuestionId = null;
    public string $answer_text = '';
    public ?float $recorded_seconds = null;
    public $audio;
    public $video;
    public string $noteBody = '';
    public string $noteQuestion = '';

    public function mount(InterviewSession $session): void
    {
        Gate::authorize('view', $session);
        $this->sessionId = $session->id;
    }

    public function rendering(): void
    {
        $questionId = $this->currentQuestion?->id;
        if ($questionId !== $this->draftQuestionId) {
            if ($this->draftQuestionId !== null && ($this->answer_text !== '' || $this->audio || $this->video)) {
                $this->addError('answer', 'Pertanyaan aktif berubah di sesi lain. Draf lama dibersihkan agar tidak terkirim ke pertanyaan berikutnya.');
            }
            $this->reset('answer_text', 'recorded_seconds', 'audio', 'video');
            $this->draftQuestionId = $questionId;
        }
    }

    #[Computed]
    public function interview(): InterviewSession
    {
        return InterviewSession::query()->forUser(Auth::user())->with(['questions', 'student', 'notes.reviewer'])->findOrFail($this->sessionId);
    }

    #[Computed]
    public function currentQuestion(): ?InterviewQuestion
    {
        return $this->interview->questions->first(fn (InterviewQuestion $question) => $question->answered_at === null);
    }

    public function openQuestion(SubmitInterviewAnswer $answers): void
    {
        $this->resetValidation();
        $answers->open(Auth::user(), $this->sessionId);
        unset($this->interview, $this->currentQuestion);
    }

    public function sendAnswer(int $questionId, SubmitInterviewAnswer $answers): bool
    {
        $this->resetValidation();
        $answers->handle(Auth::user(), $this->sessionId, $questionId, $this->only(['answer_text', 'recorded_seconds']), $this->audio, $this->video);
        $this->reset('answer_text', 'recorded_seconds', 'audio', 'video');
        unset($this->interview, $this->currentQuestion);

        return true;
    }

    public function transcribe(int $questionId, SubmitInterviewAnswer $answers): bool
    {
        $this->resetValidation('audio');
        $this->answer_text = $answers->transcribe(Auth::user(), $this->sessionId, $questionId, $this->audio);

        return true;
    }

    public function retry(ManageInterviewSession $sessions): void
    {
        $this->resetValidation();
        $sessions->retry(Auth::user(), $this->sessionId);
        unset($this->interview);
    }

    public function cancel(ManageInterviewSession $sessions): void
    {
        $sessions->cancel(Auth::user(), $this->sessionId);
        unset($this->interview, $this->currentQuestion);
    }

    public function deleteRecordings(ManageInterviewSession $sessions): void
    {
        $sessions->deleteRecordings(Auth::user(), $this->sessionId);
        unset($this->interview);
        \Flux\Flux::toast('Rekaman dihapus. Transkrip dan hasil latihan tetap tersedia.');
    }

    public function addNote(ManageInterviewSession $sessions): void
    {
        $this->resetValidation();
        $this->validate(['noteQuestion' => ['nullable', 'integer']]);
        $sessions->note(Auth::user(), $this->sessionId, $this->noteBody, $this->noteQuestion === '' ? null : (int) $this->noteQuestion);
        $this->reset('noteBody', 'noteQuestion');
        unset($this->interview);
        \Flux\Flux::toast('Catatan bimbingan disimpan.');
    }
}; ?>

<section class="mx-auto flex w-full max-w-5xl flex-col gap-6">
    <header class="space-y-3">
        <flux:button :href="route('interviews.index')" variant="ghost" icon="arrow-left" wire:navigate>Daftar latihan</flux:button>
        <div class="flex flex-wrap items-start justify-between gap-3"><div class="min-w-0 space-y-2"><flux:heading size="xl" level="1">{{ $this->interview->target_position }}</flux:heading><flux:text>{{ $this->interview->student->name }} · {{ $this->interview->question_count }} pertanyaan · {{ ['text' => 'Teks', 'voice' => 'Suara', 'video' => 'Video'][$this->interview->interaction_mode] }}</flux:text></div><flux:badge :color="$this->interview->status->color()">{{ $this->interview->status->label() }}</flux:badge></div>
    </header>
    @if ($errors->any())<div role="alert" class="rounded-xl bg-red-50 p-4 text-sm text-red-800 dark:bg-red-950 dark:text-red-200">{{ $errors->first() }}</div>@endif

    @if (in_array($this->interview->status, [InterviewStatus::Preparing, InterviewStatus::Evaluating], true))
        <div wire:poll.3s class="space-y-3 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700" role="status">
            <flux:heading size="lg" level="2">{{ $this->interview->status === InterviewStatus::Preparing ? 'Menyiapkan pertanyaan latihan' : 'Menyusun umpan balik' }}</flux:heading>
            <flux:text>{{ $this->interview->status === InterviewStatus::Preparing ? 'Sesi akan siap setelah pertanyaan selesai dibuat. Anda boleh kembali ke daftar dan membuka sesi ini lagi.' : 'Jawaban Anda sudah tersimpan. Hasil akan muncul otomatis setelah evaluasi selesai.' }}</flux:text>
            <flux:text>Jika proses tidak berlanjut, hubungi administrator untuk memeriksa layanan pemrosesan.</flux:text>
        </div>
    @elseif ($this->interview->status === InterviewStatus::Failed)
        <div role="status" class="space-y-4 rounded-xl bg-amber-50 p-5 text-amber-950 dark:bg-amber-950 dark:text-amber-100"><p>{{ $this->interview->error_message }}</p>@can('manage', $this->interview)<flux:button wire:click="retry" :disabled="$this->interview->retry_count >= config('interviews.max_retries')" wire:loading.attr="disabled">Coba proses lagi</flux:button><p class="text-sm">Percobaan ulang {{ $this->interview->retry_count }} / {{ config('interviews.max_retries') }}.</p>@endcan</div>
    @elseif ($this->interview->status === InterviewStatus::Active)
        @can('update', $this->interview)
            @if ($this->currentQuestion && ! $this->currentQuestion->opened_at)
                <div class="space-y-4 border-y border-zinc-200 py-8 dark:border-zinc-700"><flux:heading size="lg" level="2">Siap untuk pertanyaan {{ $this->currentQuestion->sequence }}?</flux:heading><flux:text>Waktu {{ $this->interview->answer_seconds }} detik dimulai ketika pertanyaan dibuka. Jawaban yang sudah dikirim tersimpan dan tidak dapat diubah.</flux:text><flux:button wire:click="openQuestion" variant="primary" wire:loading.attr="disabled">Buka pertanyaan {{ $this->currentQuestion->sequence }}</flux:button></div>
            @elseif ($this->currentQuestion)
                @php($question = $this->currentQuestion)
                <div wire:key="answer-{{ $question->id }}" x-data="interviewRecorder({ questionId: {{ $question->id }}, deadline: {{ $question->opened_at->addSeconds($this->interview->answer_seconds)->getTimestampMs() }}, serverNow: {{ now()->getTimestampMs() }}, maxSeconds: {{ $this->interview->answer_seconds }}, mode: @js($this->interview->interaction_mode), voiceUrl: @js(route('interviews.voice', $this->interview)) })" class="space-y-6">
                    <div class="space-y-4 rounded-xl bg-zinc-50 p-5 sm:p-6 dark:bg-zinc-800">
                        <div class="flex items-center justify-between gap-3"><p class="text-sm font-medium">Pertanyaan {{ $question->sequence }} dari {{ $this->interview->question_count }}</p><span class="font-mono text-sm tabular-nums" aria-label="Sisa waktu" x-text="timeLabel"></span></div>
                        <flux:heading size="lg" level="2">{{ $question->prompt }}</flux:heading>
                        @if ($this->interview->interaction_mode !== 'text')<flux:button type="button" x-on:click="speak" x-bind:disabled="speaking || busy || recording" icon="speaker-wave">Dengarkan suara AI</flux:button><p class="text-xs text-zinc-600 dark:text-zinc-300">Suara pewawancara dibuat oleh AI.</p>@endif
                    </div>
                    <div x-show="error" x-cloak role="alert" class="rounded-xl bg-red-50 p-4 text-sm text-red-800 dark:bg-red-950 dark:text-red-200" x-text="error"></div>
                    <p x-show="remaining === 0" x-cloak role="status" class="text-sm text-amber-800 dark:text-amber-200">Waktu menjawab habis. Jawaban ditandai melewati waktu. Selesaikan unggahan atau kirim jawaban yang sudah ada.</p>
                    @if ($this->interview->interaction_mode !== 'text')
                        <div class="space-y-4">
                            @if ($this->interview->interaction_mode === 'video')<video x-ref="preview" wire:ignore autoplay muted playsinline class="aspect-video w-full max-w-lg rounded-xl bg-zinc-900" aria-label="Pratinjau kamera Anda"></video>@endif
                            <div class="flex flex-wrap items-center gap-3"><flux:button type="button" x-on:click="startRecording" x-bind:disabled="recording || busy || remaining === 0" icon="microphone">Mulai rekam jawaban</flux:button><flux:button type="button" x-on:click="stopRecording" x-bind:disabled="!recording || busy" icon="stop">Selesai rekam</flux:button><span x-show="recording" role="status" class="text-sm font-medium text-red-700 dark:text-red-300">Sedang merekam</span></div>
                            <p role="status" class="text-sm text-zinc-600 dark:text-zinc-300" x-text="status"></p>
                            <div x-show="audioPreview" x-cloak class="space-y-2"><audio x-bind:src="audioPreview" controls class="w-full max-w-lg" aria-label="Pratinjau rekaman jawaban"></audio><a x-bind:href="audioPreview" download="jawaban-wawancara.webm" class="text-sm text-blue-700 underline underline-offset-4 dark:text-blue-300">Simpan salinan audio di perangkat</a></div>
                            <flux:text>Izinkan mikrofon{{ $this->interview->interaction_mode === 'video' ? ' dan kamera' : '' }}. Setelah berhenti, audio akan ditranskripsikan. Periksa transkrip sebelum mengirim; jika perangkat tidak mendukung rekaman, Anda tetap dapat mengetik jawaban.</flux:text>
                        </div>
                    @endif
                    <form x-on:submit.prevent="submit" class="space-y-4">
                        <flux:textarea name="answer_text" wire:model="answer_text" label="Jawaban / transkrip" rows="7" maxlength="10000" placeholder="Jelaskan situasi, tugas, tindakan yang Anda lakukan, dan hasilnya." x-bind:disabled="recording || busy" />
                        <div class="flex flex-wrap gap-3"><flux:button type="submit" variant="primary" x-bind:disabled="recording || busy || submitting" wire:loading.attr="disabled">{{ $question->sequence === $this->interview->question_count ? 'Kirim & evaluasi latihan' : 'Kirim jawaban' }}</flux:button>@if ($audio)<flux:button type="button" wire:click="transcribe({{ $question->id }})" wire:loading.attr="disabled" x-bind:disabled="recording || busy || submitting">Transkripsikan ulang</flux:button>@endif</div>
                    </form>
                </div>
            @endif
        @else
            <flux:text>Mahasiswa sedang menjalankan sesi. Transkrip jawaban yang sudah dikirim tersedia di bawah.</flux:text>
        @endcan
    @elseif ($this->interview->status === InterviewStatus::Cancelled)
        <flux:text>Sesi dibatalkan. Jawaban yang sudah dikirim tetap tersedia dalam riwayat.</flux:text>
    @endif

    @if ($this->interview->status === InterviewStatus::Completed)
        <div class="space-y-5 border-y border-zinc-200 py-6 dark:border-zinc-700">
            <div class="flex flex-wrap items-center justify-between gap-3"><flux:heading size="lg" level="2">Hasil latihan</flux:heading><p class="text-2xl font-semibold tabular-nums">{{ number_format($this->interview->overall_score, 1) }} <span class="text-base font-normal text-zinc-600 dark:text-zinc-400">/ 100</span></p></div>
            <p class="max-w-prose whitespace-pre-line text-sm leading-relaxed">{{ $this->interview->evaluation['summary'] ?? '' }}</p>
            <dl class="grid gap-4 sm:grid-cols-3">@foreach (['star_score' => 'Struktur & relevansi STAR', 'technical_score' => 'Ketepatan teknis', 'communication_score' => 'Kejelasan komunikasi'] as $key => $label)<div wire:key="score-{{ $key }}"><dt class="text-sm text-zinc-600 dark:text-zinc-400">{{ $label }}</dt><dd class="mt-1 text-xl font-semibold tabular-nums">{{ number_format($this->interview->{$key}, 1) }}</dd></div>@endforeach</dl>
            <flux:text>Skor gabungan: 40% struktur STAR, 40% ketepatan teknis, dan 20% kejelasan transkrip. Ini panduan latihan, bukan nilai akademik atau keputusan rekrutmen. AI tidak menilai emosi atau kepercayaan diri dari rekaman.</flux:text>
            <div class="grid gap-6 sm:grid-cols-2">@foreach (['strengths' => 'Yang sudah baik', 'improvements' => 'Fokus latihan berikutnya'] as $key => $label)<div wire:key="feedback-{{ $key }}" class="space-y-2"><flux:heading level="3">{{ $label }}</flux:heading><ul class="list-disc space-y-2 pl-5 text-sm leading-relaxed">@foreach ($this->interview->evaluation[$key] ?? [] as $item)<li wire:key="{{ $key }}-{{ $loop->index }}">{{ $item }}</li>@endforeach</ul></div>@endforeach</div>
        </div>
    @endif

    @if ($this->interview->questions->whereNotNull('answered_at')->isNotEmpty())
        <div class="space-y-4"><flux:heading size="lg" level="2">Transkrip & umpan balik per pertanyaan</flux:heading>
            @foreach ($this->interview->questions->whereNotNull('answered_at') as $answered)
                <details wire:key="transcript-{{ $answered->id }}" class="border-b border-zinc-200 pb-4 dark:border-zinc-700"><summary class="cursor-pointer rounded py-2 font-medium focus-visible:outline-2 focus-visible:outline-blue-600">{{ $answered->sequence }}. {{ $answered->prompt }}</summary><div class="space-y-4 pt-4">
                    @if ($answered->timed_out)<flux:badge color="amber">Melewati waktu</flux:badge>@endif
                    <p class="whitespace-pre-wrap break-words text-sm leading-relaxed">{{ $answered->answer_text ?: 'Tidak ada jawaban yang dikirim.' }}</p>
                    @if ($answered->video_path)<video src="{{ route('interviews.media', ['question' => $answered, 'kind' => 'video']) }}" controls preload="none" playsinline class="aspect-video w-full max-w-xl rounded-xl bg-zinc-900" aria-label="Rekaman jawaban pertanyaan {{ $answered->sequence }}"></video>@elseif ($answered->audio_path)<audio src="{{ route('interviews.media', ['question' => $answered, 'kind' => 'audio']) }}" controls preload="none" class="w-full max-w-lg" aria-label="Rekaman jawaban pertanyaan {{ $answered->sequence }}"></audio>@endif
                    @if ($answered->evaluation)<div class="space-y-3 rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800"><p class="text-sm leading-relaxed">{{ $answered->evaluation['feedback'] ?? '' }}</p><flux:heading level="3">Contoh jawaban untuk latihan</flux:heading><p class="whitespace-pre-wrap text-sm leading-relaxed">{{ $answered->evaluation['improved_answer'] ?? '' }}</p><p class="text-xs text-zinc-600 dark:text-zinc-300">{{ $answered->evaluation['word_count'] ?? 0 }} kata · {{ $answered->evaluation['filler_count'] ?? 0 }} kata pengisi terdeteksi di transkrip @if (($answered->evaluation['words_per_minute'] ?? null) !== null) · Estimasi {{ $answered->evaluation['words_per_minute'] }} kata/menit menurut durasi rekaman perangkat @endif</p></div>@endif
                </div></details>
            @endforeach
        </div>
    @endif

    @if ($this->interview->status === InterviewStatus::Completed)
        <div class="space-y-4"><flux:heading size="lg" level="2">Catatan dosen pembimbing</flux:heading>
            @can('review', $this->interview)<form wire:submit="addNote" class="space-y-4"><flux:select wire:model="noteQuestion" label="Catatan untuk"><option value="">Keseluruhan sesi</option>@foreach ($this->interview->questions as $noteTarget)<option wire:key="note-target-{{ $noteTarget->id }}" value="{{ $noteTarget->id }}">Pertanyaan {{ $noteTarget->sequence }}</option>@endforeach</flux:select><flux:textarea name="body" wire:model="noteBody" label="Catatan bimbingan" rows="3" maxlength="5000" required /><flux:button type="submit" variant="primary" wire:loading.attr="disabled">Simpan catatan</flux:button></form>@endcan
            @forelse ($this->interview->notes as $note)<div wire:key="note-{{ $note->id }}" class="space-y-2 border-t border-zinc-200 pt-4 dark:border-zinc-700"><p class="text-sm font-medium">{{ $note->reviewer->name }} · {{ $note->interview_question_id ? 'Pertanyaan '.($this->interview->questions->firstWhere('id', $note->interview_question_id)?->sequence) : 'Keseluruhan sesi' }}</p><p class="whitespace-pre-wrap break-words text-sm leading-relaxed">{{ $note->body }}</p></div>@empty<flux:text>Belum ada catatan bimbingan.</flux:text>@endforelse
        </div>
    @endif
    @can('manage', $this->interview)
        <footer class="flex flex-wrap gap-3 border-t border-zinc-200 pt-5 dark:border-zinc-700">
            @if (! in_array($this->interview->status, [InterviewStatus::Completed, InterviewStatus::Cancelled], true))<flux:button wire:click="cancel" wire:confirm="Batalkan sesi ini? Jawaban yang sudah dikirim tetap tersimpan." wire:loading.attr="disabled">Batalkan sesi</flux:button>@endif
            @if ($this->interview->questions->contains(fn ($question) => $question->audio_path || $question->video_path))<flux:button wire:click="deleteRecordings" wire:confirm="Hapus semua rekaman audio dan video sesi ini? Transkrip dan hasil tetap tersimpan." wire:loading.attr="disabled">Hapus rekaman</flux:button>@endif
        </footer>
    @endcan
</section>
