<?php

use App\Actions\SaveIndustryEvaluation;
use App\Models\IndustryEvaluation;
use App\Models\InternshipPlacement;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Evaluasi Kinerja Industri')] class extends Component
{
    use WithPagination;

    public string $placementId = '';

    public array $form = [];

    public function mount(): void
    {
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->form = ['period_type' => 'monthly', 'period_month' => today()->format('Y-m'), 'feedback' => '', 'improvement_plan' => ''];
        foreach (array_keys(IndustryEvaluation::rubric()) as $field) {
            $this->form[$field] = '';
        }
        $this->resetValidation();
    }

    public function editEvaluation(int $id): void
    {
        $evaluation = IndustryEvaluation::query()->forUser(Auth::user())->with('placement')->findOrFail($id);
        Gate::authorize('update', $evaluation);
        $this->placementId = (string) $evaluation->internship_placement_id;
        $this->form = $evaluation->only([...array_keys(IndustryEvaluation::rubric()), 'period_type', 'feedback', 'improvement_plan']);
        $this->form['period_month'] = $evaluation->period_month->format('Y-m');
        $this->resetValidation();
    }

    public function save(SaveIndustryEvaluation $save): void
    {
        Validator::make(['placement_id' => $this->placementId], ['placement_id' => ['required', 'integer']])->validate();
        $save->handle(Auth::user(), (int) $this->placementId, $this->form);
        $this->resetForm();
        $this->resetPage();
        unset($this->evaluations);
        Flux::toast(variant: 'success', text: 'Evaluasi diterbitkan untuk mahasiswa dan dosen pembimbing.');
    }

    #[Computed]
    public function placements(): \Illuminate\Database\Eloquent\Collection
    {
        return InternshipPlacement::query()->forUser(Auth::user())->with('student')->orderByDesc('starts_on')->get();
    }

    #[Computed]
    public function evaluations(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return IndustryEvaluation::query()->forUser(Auth::user())->with(['placement.student', 'mentor'])
            ->orderByDesc('period_month')->orderByDesc('id')->paginate(10);
    }
}; ?>

<section class="mx-auto flex w-full max-w-6xl flex-col gap-7">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div><flux:heading size="xl" level="1">Evaluasi Kinerja Industri</flux:heading><flux:text class="mt-2">Penilaian bulanan dan akhir magang berdasarkan lima kriteria dengan bobot yang sama.</flux:text></div>
        <flux:button :href="route('industry.index')" wire:navigate>Rekap magang</flux:button>
    </header>
    @if ($errors->any())<div role="alert" class="rounded-lg bg-red-50 p-4 text-sm text-red-800 dark:bg-red-950 dark:text-red-200"><ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li wire:key="evaluation-error-{{ $loop->index }}">{{ $error }}</li>@endforeach</ul></div>@endif

    @if (auth()->user()->isMentorDudi())
        <form wire:submit="save" class="flex flex-col gap-6 rounded-xl border border-zinc-200 p-5 sm:p-6 dark:border-zinc-700">
            <div><flux:heading size="lg" level="2">Isi evaluasi mahasiswa</flux:heading><flux:text class="mt-2">Pilih periode dengan cermat. Menyimpan kembali periode yang sama memperbarui evaluasinya. Evaluasi akhir tersedia sejak hari terakhir magang.</flux:text></div>
            <div class="grid gap-4 sm:grid-cols-3">
                <flux:select wire:model="placementId" name="placement_id" label="Mahasiswa / penempatan" required>
                    <option value="">Pilih mahasiswa</option>
                    @foreach ($this->placements as $placement)<option wire:key="evaluation-placement-{{ $placement->id }}" value="{{ $placement->id }}">{{ $placement->student->name }} · {{ $placement->company_name }}</option>@endforeach
                </flux:select>
                <flux:select wire:model.live="form.period_type" name="period_type" label="Jenis evaluasi"><option value="monthly">Bulanan</option><option value="final">Akhir magang</option></flux:select>
                @if ($form['period_type'] === 'monthly')<flux:input wire:model="form.period_month" name="period_month" label="Bulan penilaian" type="month" required />@endif
            </div>
            <div class="flex flex-col divide-y divide-zinc-200 dark:divide-zinc-700">
                @foreach (IndustryEvaluation::rubric() as $field => $criterion)
                    <div wire:key="rubric-{{ $field }}" class="grid items-start gap-3 py-4 sm:grid-cols-2">
                        <div><flux:heading level="3">{{ $criterion['label'] }}</flux:heading><flux:text class="mt-1">{{ $criterion['description'] }}</flux:text></div>
                        <flux:select wire:model="form.{{ $field }}" :name="$field" :label="'Skor '.$criterion['label']" label:sr-only required>
                            <option value="">Pilih skor 1–5</option>
                            @foreach (IndustryEvaluation::scoreLabels() as $score => $label)<option wire:key="score-{{ $field }}-{{ $score }}" value="{{ $score }}">{{ $score }} · {{ $label }}</option>@endforeach
                        </flux:select>
                    </div>
                @endforeach
            </div>
            <flux:text>Nilai akhir = jumlah lima skor × 4, pada skala 20–100. Skor 3 berarti memenuhi standar; belum dinilai ditampilkan tanpa nilai.</flux:text>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:textarea wire:model="form.feedback" name="feedback" label="Capaian & bukti pengamatan" description="Sebutkan contoh perilaku atau hasil kerja yang mendukung penilaian." rows="4" maxlength="3000" required />
                <flux:textarea wire:model="form.improvement_plan" name="improvement_plan" label="Rencana pengembangan" description="Tuliskan langkah perbaikan yang dapat dilakukan mahasiswa." rows="4" maxlength="3000" required />
            </div>
            <div class="flex flex-wrap gap-3"><flux:button type="submit" variant="primary" :disabled="$this->placements->isEmpty()" wire:loading.attr="disabled">Terbitkan evaluasi</flux:button><flux:button type="button" wire:click="resetForm">Kosongkan formulir</flux:button></div>
        </form>
    @endif

    <div class="flex flex-col gap-5">
        <flux:heading size="lg" level="2">Hasil evaluasi</flux:heading>
        @forelse ($this->evaluations as $evaluation)
            <article wire:key="evaluation-{{ $evaluation->id }}" class="flex flex-col gap-4 border-b border-zinc-200 pb-6 dark:border-zinc-700">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div><flux:heading level="3">{{ $evaluation->placement->student->name }}</flux:heading><flux:text class="mt-1">{{ $evaluation->placement->company_name }} · {{ $evaluation->period_type === 'final' ? 'Evaluasi akhir magang' : $evaluation->period_month->translatedFormat('F Y') }} · Revisi {{ $evaluation->revision }}</flux:text></div>
                    <flux:badge color="blue">{{ number_format($evaluation->final_score, 0) }} / 100</flux:badge>
                </div>
                <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2">
                    @foreach (IndustryEvaluation::rubric() as $field => $criterion)<div wire:key="evaluation-{{ $evaluation->id }}-{{ $field }}" class="flex justify-between gap-3 text-sm"><dt class="text-zinc-600 dark:text-zinc-300">{{ $criterion['label'] }}</dt><dd class="font-medium tabular-nums">{{ $evaluation->$field }} / 5</dd></div>@endforeach
                </dl>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div><flux:heading level="4">Umpan balik mentor</flux:heading><p class="mt-2 whitespace-pre-wrap break-words text-sm">{{ $evaluation->feedback }}</p></div>
                    <div><flux:heading level="4">Rencana pengembangan</flux:heading><p class="mt-2 whitespace-pre-wrap break-words text-sm">{{ $evaluation->improvement_plan }}</p></div>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-3"><flux:text>Dinilai oleh {{ $evaluation->mentor->name }}</flux:text>@can('update', $evaluation)<flux:button wire:click="editEvaluation({{ $evaluation->id }})" size="sm">Ubah evaluasi</flux:button>@endcan</div>
            </article>
        @empty
            <div class="border-y border-dashed border-zinc-300 py-8 dark:border-zinc-700"><flux:heading level="3">Evaluasi belum tersedia</flux:heading><flux:text class="mt-2">Mentor penempatan mengisi evaluasi bulanan selama magang dan evaluasi akhir setelah masa magang selesai.</flux:text></div>
        @endforelse
        {{ $this->evaluations->links() }}
    </div>
</section>
