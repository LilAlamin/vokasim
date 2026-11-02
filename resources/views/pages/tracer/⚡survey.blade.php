<?php

use App\Actions\SaveTracerResponse;
use App\Models\PartnerCompany;
use App\Models\TracerResponse;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Survei Alumni')] class extends Component
{
    public array $form = [];

    public string $skillGaps = '';

    public bool $consent = false;

    public function mount(): void
    {
        Gate::authorize('create', TracerResponse::class);
        $response = TracerResponse::query()->where('user_id', Auth::id())->first();
        $this->form = ['graduation_year' => today()->year, 'graduated_on' => '', 'employment_status' => 'seeking', 'employer_name' => '', 'job_title' => '', 'partner_company_id' => '', 'first_job_started_on' => '', 'first_monthly_salary' => '', 'field_alignment' => 'unknown', 'curriculum_feedback' => ''];

        if ($response) {
            $this->form = [...$this->form, ...$response->only(array_keys($this->form))];
            $this->form['graduated_on'] = $response->graduated_on->toDateString();
            $this->form['first_job_started_on'] = $response->first_job_started_on?->toDateString() ?? '';
            $this->skillGaps = implode(', ', $response->skill_gaps ?? []);
        }
    }

    public function save(SaveTracerResponse $save): void
    {
        $skills = array_values(array_filter(array_map('trim', preg_split('/[,;\r\n]+/u', $this->skillGaps) ?: [])));
        $save->handle(Auth::user(), [...$this->form, 'skill_gaps' => $skills, 'consent' => $this->consent]);
        $this->resetValidation();
        unset($this->lastResponse);
        Flux::toast(variant: 'success', text: 'Jawaban tracer study tersimpan. Anda dapat memperbaruinya saat situasi karier berubah.');
    }

    #[Computed]
    public function companies(): \Illuminate\Database\Eloquent\Collection
    {
        Gate::authorize('create', TracerResponse::class);

        return PartnerCompany::query()->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function lastResponse(): ?TracerResponse
    {
        Gate::authorize('create', TracerResponse::class);

        return TracerResponse::query()->where('user_id', Auth::id())->first();
    }
}; ?>

<section class="mx-auto flex w-full max-w-3xl flex-col gap-7">
    <header><flux:heading size="xl" level="1">Survei Alumni</flux:heading><flux:text class="mt-2">Bagikan perkembangan karier setelah lulus untuk membantu institusi menilai keselarasan pendidikan dan kebutuhan industri.</flux:text></header>
    @if ($this->lastResponse)<div role="status" class="rounded-lg bg-blue-50 p-4 text-sm text-blue-900 dark:bg-blue-950 dark:text-blue-200">Jawaban terakhir diperbarui {{ $this->lastResponse->updated_at->format('d M Y H:i') }}. Formulir ini memperbarui jawaban Anda yang sama.</div>@endif
    @if ($errors->any())<div role="alert" class="rounded-lg bg-red-50 p-4 text-sm text-red-800 dark:bg-red-950 dark:text-red-200"><ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li wire:key="tracer-error-{{ $loop->index }}">{{ $error }}</li>@endforeach</ul></div>@endif

    <form wire:submit="save" class="flex flex-col gap-7">
        <fieldset class="flex flex-col gap-4">
            <legend class="mb-4 text-lg font-semibold">Kelulusan</legend>
            <flux:text>{{ auth()->user()->name }} · {{ auth()->user()->study_program ?: 'Program studi belum tercatat pada profil' }}</flux:text>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="form.graduation_year" name="graduation_year" label="Tahun kelulusan" type="number" min="1950" :max="today()->year" required />
                <flux:input wire:model="form.graduated_on" name="graduated_on" label="Tanggal lulus" type="date" :max="today()->toDateString()" required />
            </div>
        </fieldset>
        <fieldset class="flex flex-col gap-4 border-t border-zinc-200 pt-6 dark:border-zinc-700">
            <legend class="text-lg font-semibold">Situasi saat ini</legend>
            <flux:select wire:model.live="form.employment_status" name="employment_status" label="Status kegiatan setelah lulus" required>
                @foreach (TracerResponse::employmentStatuses() as $value => $label)<option wire:key="employment-{{ $value }}" value="{{ $value }}">{{ $label }}</option>@endforeach
            </flux:select>
            @if (in_array($form['employment_status'], ['employed', 'entrepreneur']))
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="form.employer_name" name="employer_name" label="Nama perusahaan / usaha" maxlength="255" required />
                    <flux:input wire:model="form.job_title" name="job_title" label="Posisi / peran" maxlength="255" required />
                    <flux:select wire:model="form.partner_company_id" name="partner_company_id" label="Perusahaan mitra institusi (opsional)">
                        <option value="">Bukan mitra / belum tercatat</option>
                        @foreach ($this->companies as $company)<option wire:key="tracer-partner-{{ $company->id }}" value="{{ $company->id }}">{{ $company->name }}</option>@endforeach
                    </flux:select>
                    <flux:select wire:model="form.field_alignment" name="field_alignment" label="Keselarasan dengan bidang studi">
                        @foreach (TracerResponse::alignmentLabels() as $value => $label)<option wire:key="alignment-{{ $value }}" value="{{ $value }}">{{ $label }}</option>@endforeach
                    </flux:select>
                </div>
            @endif
        </fieldset>
        <fieldset class="flex flex-col gap-4 border-t border-zinc-200 pt-6 dark:border-zinc-700">
            <legend class="text-lg font-semibold">Pekerjaan pertama</legend>
            <flux:text>Isi jika pernah bekerja atau berwirausaha, termasuk bila sekarang sedang mencari pekerjaan atau melanjutkan studi. Masa tunggu dihitung dari tanggal lulus; mulai bekerja sebelum lulus dihitung 0 bulan.</flux:text>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="form.first_job_started_on" name="first_job_started_on" label="Tanggal pertama mulai bekerja" type="date" :max="today()->toDateString()" :required="in_array($form['employment_status'], ['employed', 'entrepreneur'])" />
                <flux:input wire:model="form.first_monthly_salary" name="first_monthly_salary" label="Gaji / pendapatan pertama per bulan (Rp)" description="Opsional. Kosongkan jika tidak ingin membagikan." type="number" min="0" max="1000000000" step="1" />
            </div>
        </fieldset>
        <fieldset class="flex flex-col gap-4 border-t border-zinc-200 pt-6 dark:border-zinc-700">
            <legend class="text-lg font-semibold">Kebutuhan keahlian</legend>
            <flux:textarea wire:model="skillGaps" name="skill_gaps" label="Keahlian yang perlu ditingkatkan" description="Pisahkan dengan koma atau baris baru. Maksimal 15 keahlian, masing-masing 80 karakter." placeholder="Contoh: bahasa Inggris, pemrograman PLC, komunikasi pelanggan" rows="3" maxlength="1215" />
            <flux:textarea wire:model="form.curriculum_feedback" name="curriculum_feedback" label="Masukan untuk kurikulum (opsional)" rows="4" maxlength="3000" />
        </fieldset>
        <div class="flex flex-col gap-5 border-t border-zinc-200 pt-6 dark:border-zinc-700">
            <flux:checkbox wire:model="consent" label="Saya menyetujui jawaban ini digunakan oleh manajemen institusi untuk tracer study dan analisis agregat." />
            <flux:text>Jawaban hanya dapat diubah melalui akun Anda. Data gaji bersifat opsional dan ditampilkan sebagai rata-rata pada analitik manajemen.</flux:text>
            <div><flux:button type="submit" variant="primary" wire:loading.attr="disabled">Simpan jawaban survei</flux:button></div>
        </div>
    </form>
</section>
