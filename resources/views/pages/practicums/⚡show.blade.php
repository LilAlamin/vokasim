<?php

use App\Actions\EnrollPracticumStudent;
use App\Actions\RecordPracticumAttendance;
use App\Actions\SavePracticumAssignment;
use App\Actions\SavePracticumMaterial;
use App\Actions\SavePracticumSession;
use App\Enums\UserRole;
use App\Models\PracticumEnrollment;
use App\Models\PracticumMaterial;
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
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Title('Kelas Praktikum')] class extends Component {
    use WithFileUploads, WithPagination;

    #[Locked]
    public int $moduleId;
    #[Url]
    public string $tab = 'schedule';
    #[Locked]
    public ?int $editingSessionId = null;
    #[Locked]
    public ?int $editingAssignmentId = null;
    #[Locked]
    public ?int $selectedMaterialId = null;
    public array $materialForm = ['title' => '', 'kind' => 'material'];
    public ?TemporaryUploadedFile $materialFile = null;
    public array $sessionForm = [];
    public array $assignmentForm = [];
    public bool $showSessionForm = false;
    public bool $showAssignmentForm = false;
    public string $studentId = '';
    public string $studentSearch = '';
    public string $attendanceSessionId = '';
    public array $attendanceStates = [];
    public array $attendanceNotes = [];

    public function mount(PracticumModule $module): void
    {
        Gate::authorize('view', $module);
        $this->moduleId = $module->id;
        $this->resetSessionForm();
        $this->resetAssignmentForm();
    }

    #[Computed]
    public function module(): PracticumModule
    {
        $module = PracticumModule::query()->forUser(Auth::user())->with('lecturer')->findOrFail($this->moduleId);
        Gate::authorize('view', $module);

        return $module;
    }

    #[Computed]
    public function canManage(): bool
    {
        return Gate::allows('manage', $this->module);
    }

    public function selectTab(string $tab): void
    {
        abort_unless(in_array($tab, ['schedule', 'materials', 'assignments', 'progress'], true), 404);
        Gate::authorize('view', $this->module);
        $this->tab = $tab;
        $this->resetValidation();
    }

    public function resetSessionForm(): void
    {
        $this->editingSessionId = null;
        $this->showSessionForm = false;
        $this->sessionForm = ['title' => '', 'room' => '', 'starts_at' => '', 'ends_at' => '', 'is_exam' => false, 'cancelled' => false];
        $this->resetValidation();
    }

    public function createSession(): void
    {
        Gate::authorize('manage', $this->module);
        $this->resetSessionForm();
        $this->showSessionForm = true;
    }

    public function editSession(int $id): void
    {
        Gate::authorize('manage', $this->module);
        $session = $this->module->sessions()->findOrFail($id);
        $this->editingSessionId = $session->id;
        $this->sessionForm = $session->only(['title', 'room', 'is_exam', 'cancelled']);
        $this->sessionForm['starts_at'] = $session->starts_at->setTimezone($this->module->timezone)->format('Y-m-d\TH:i');
        $this->sessionForm['ends_at'] = $session->ends_at->setTimezone($this->module->timezone)->format('Y-m-d\TH:i');
        $this->showSessionForm = true;
        $this->resetValidation();
    }

    public function saveSession(SavePracticumSession $save): void
    {
        $save->handle(Auth::user(), $this->moduleId, $this->sessionForm, $this->editingSessionId);
        $this->resetSessionForm();
        unset($this->sessions, $this->attendanceSessions);
        Flux::toast(variant: 'success', text: 'Jadwal sesi tersimpan.');
    }

    public function checkIn(int $id, RecordPracticumAttendance $record): void
    {
        $this->module->sessions()->findOrFail($id);
        $record->checkIn(Auth::user(), $id);
        unset($this->sessions, $this->progress);
        Flux::toast(variant: 'success', text: 'Kehadiran Anda tercatat.');
    }

    public function uploadMaterial(SavePracticumMaterial $save): void
    {
        $save->handle(Auth::user(), $this->moduleId, $this->materialForm, $this->materialFile);
        $this->materialForm = ['title' => '', 'kind' => 'material'];
        $this->materialFile = null;
        unset($this->materials);
        Flux::toast(variant: 'success', text: 'Materi berhasil diunggah.');
    }

    public function removeMaterial(int $id, SavePracticumMaterial $save): void
    {
        $this->module->materials()->findOrFail($id);
        $save->remove(Auth::user(), $id);
        $this->selectedMaterialId = null;
        unset($this->materials, $this->selectedMaterial);
        Flux::toast(variant: 'success', text: 'Materi dihapus dari kelas.');
    }

    public function previewMaterial(int $id): void
    {
        $material = $this->module->materials()->findOrFail($id);
        Gate::authorize('view', $material);
        $this->selectedMaterialId = $material->id;
    }

    public function resetAssignmentForm(): void
    {
        $this->editingAssignmentId = null;
        $this->showAssignmentForm = false;
        $this->assignmentForm = ['title' => '', 'instructions' => '', 'due_at' => '', 'weight' => 1, 'allow_late' => false, 'criteria' => [
            ['title' => 'Ketepatan prosedur dan hasil', 'category' => 'hard_skill', 'max_score' => 100, 'weight' => 70],
            ['title' => 'Kedisiplinan K3 dan komunikasi', 'category' => 'soft_skill', 'max_score' => 100, 'weight' => 30],
        ]];
        $this->resetValidation();
    }

    public function createAssignment(): void
    {
        Gate::authorize('manage', $this->module);
        $this->resetAssignmentForm();
        $this->showAssignmentForm = true;
    }

    public function editAssignment(int $id): void
    {
        Gate::authorize('manage', $this->module);
        $assignment = $this->module->assignments()->with('rubricCriteria')->findOrFail($id);
        $this->editingAssignmentId = $assignment->id;
        $this->assignmentForm = $assignment->only(['title', 'instructions', 'weight', 'allow_late']);
        $this->assignmentForm['due_at'] = $assignment->due_at->setTimezone($this->module->timezone)->format('Y-m-d\TH:i');
        $this->assignmentForm['criteria'] = $assignment->rubricCriteria->map(fn ($criterion): array => $criterion->only(['title', 'category', 'max_score', 'weight']))->all();
        $this->showAssignmentForm = true;
        $this->resetValidation();
    }

    public function addCriterion(): void
    {
        Gate::authorize('manage', $this->module);

        if (count($this->assignmentForm['criteria']) < 20) {
            $this->assignmentForm['criteria'][] = ['title' => '', 'category' => 'hard_skill', 'max_score' => 100, 'weight' => 0];
        }
    }

    public function removeCriterion(int $index): void
    {
        Gate::authorize('manage', $this->module);
        unset($this->assignmentForm['criteria'][$index]);
        $this->assignmentForm['criteria'] = array_values($this->assignmentForm['criteria']);
    }

    public function saveAssignment(SavePracticumAssignment $save): void
    {
        $save->handle(Auth::user(), $this->moduleId, $this->assignmentForm, $this->editingAssignmentId);
        $this->resetAssignmentForm();
        unset($this->assignments, $this->progress);
        Flux::toast(variant: 'success', text: 'Tugas dan rubrik tersimpan.');
    }

    public function enrollStudent(EnrollPracticumStudent $enroll): void
    {
        Gate::authorize('manage', $this->module);
        $this->validate(['studentId' => ['required', 'integer', 'min:1']]);
        $enroll->handle(Auth::user(), $this->moduleId, (int) $this->studentId);
        $this->studentId = '';
        unset($this->enrollments);
        Flux::toast(variant: 'success', text: 'Mahasiswa ditambahkan ke kelas.');
    }

    public function changeEnrollment(int $id, string $status, EnrollPracticumStudent $enroll): void
    {
        Gate::authorize('manage', $this->module);
        $enrollment = $this->module->enrollments()->findOrFail($id);
        $enroll->handle(Auth::user(), $this->moduleId, $enrollment->student_id, $status);
        unset($this->enrollments, $this->progress);
        Flux::toast(variant: 'success', text: 'Status peserta diperbarui.');
    }

    public function updatedAttendanceSessionId(): void
    {
        Gate::authorize('manage', $this->module);
        $this->attendanceStates = [];
        $this->attendanceNotes = [];

        if ($this->attendanceSessionId !== '') {
            $session = $this->module->sessions()->with('attendances')->findOrFail($this->attendanceSessionId);
            $this->attendanceStates = $session->attendances->pluck('status', 'student_id')->all();
            $this->attendanceNotes = $session->attendances->pluck('note', 'student_id')->all();
        }
    }

    public function saveAttendance(int $studentId, RecordPracticumAttendance $record): void
    {
        Gate::authorize('manage', $this->module);
        $session = $this->module->sessions()->findOrFail((int) $this->attendanceSessionId);
        $record->handle(Auth::user(), $session->id, $studentId, (string) ($this->attendanceStates[$studentId] ?? ''), $this->attendanceNotes[$studentId] ?? null);
        unset($this->progress);
        Flux::toast(variant: 'success', text: 'Presensi peserta tersimpan.');
    }

    #[Computed]
    public function sessions(): LengthAwarePaginator
    {
        return $this->module->sessions()->with(['attendances' => fn ($query) => $query->where('student_id', Auth::id())])->withCount('attendances')
            ->orderByRaw('CASE WHEN ends_at >= ? THEN 0 ELSE 1 END', [now('UTC')])->orderBy('starts_at')->paginate(10, pageName: 'sessionsPage');
    }

    #[Computed]
    public function attendanceSessions(): Collection
    {
        Gate::authorize('manage', $this->module);

        return $this->module->sessions()->where('cancelled', false)->where('starts_at', '<=', now('UTC'))->latest('starts_at')->get();
    }

    #[Computed]
    public function materials(): Collection
    {
        return $this->module->materials()->orderBy('kind')->latest('id')->get();
    }

    #[Computed]
    public function selectedMaterial(): ?PracticumMaterial
    {
        return $this->selectedMaterialId ? $this->module->materials()->findOrFail($this->selectedMaterialId) : null;
    }

    #[Computed]
    public function assignments(): LengthAwarePaginator
    {
        return $this->module->assignments()->with(['submissions' => fn ($query) => $query->where('student_id', Auth::id())->latestAttempts()->with('assessment')])
            ->withCount(['submissions as pending_count' => fn ($query) => $query->latestAttempts()->where('status', 'submitted')])
            ->orderBy('due_at')->paginate(10, pageName: 'assignmentsPage');
    }

    #[Computed]
    public function enrollments(): LengthAwarePaginator
    {
        Gate::authorize('manage', $this->module);

        return $this->module->enrollments()->with('student')->latest('id')->paginate(15, pageName: 'studentsPage');
    }

    #[Computed]
    public function availableStudents(): Collection
    {
        Gate::authorize('manage', $this->module);

        return User::query()->where('role', UserRole::Mahasiswa)
            ->when($this->studentSearch !== '', fn ($query) => $query->where(fn ($query) => $query->where('name', 'like', '%'.mb_substr($this->studentSearch, 0, 100).'%')->orWhere('identifier_number', 'like', '%'.mb_substr($this->studentSearch, 0, 100).'%')))
            ->orderBy('name')->limit(50)->get(['id', 'name', 'identifier_number', 'study_program']);
    }

    #[Computed]
    public function progress(): array
    {
        $module = PracticumModule::query()->forUser(Auth::user())->with([
            'sessions.attendances' => fn ($query) => $query->when(Auth::user()->isMahasiswa(), fn ($query) => $query->where('student_id', Auth::id())),
            'assignments.submissions' => fn ($query) => $query->with('assessment')->when(Auth::user()->isMahasiswa(), fn ($query) => $query->where('student_id', Auth::id())),
        ])->findOrFail($this->moduleId);
        $studentIds = $this->canManage ? $this->enrollments->getCollection()->pluck('student_id') : collect([Auth::id()]);

        return $studentIds->mapWithKeys(fn (int $id): array => [$id => PracticumEnrollment::summarize($module, $id)])->all();
    }
}; ?>

<section class="mx-auto flex w-full max-w-6xl flex-col gap-7">
    <header class="space-y-3">
        <flux:button :href="route('practicums.index')" wire:navigate variant="ghost" size="sm">Kembali ke katalog</flux:button>
        <div class="flex flex-wrap items-center gap-3">
            <flux:heading size="xl" level="1">{{ $this->module->title }}</flux:heading>
            @if (! $this->module->published)<flux:badge>Draf</flux:badge>@endif
        </div>
        <flux:text>{{ $this->module->code }} · {{ $this->module->study_program }} · Semester {{ $this->module->semester }} · {{ $this->module->lecturer->name }}</flux:text>
        <p class="max-w-3xl whitespace-pre-line text-sm leading-6 text-zinc-600 dark:text-zinc-400">{{ $this->module->description }}</p>
        <flux:text>{{ $this->module->starts_on->format('d M Y') }} – {{ $this->module->ends_on->format('d M Y') }} · {{ $this->module->timezone }}</flux:text>
    </header>

    <nav class="flex flex-wrap gap-2 border-b border-zinc-200 pb-4 dark:border-zinc-700" aria-label="Bagian kelas praktikum">
        @foreach (['schedule' => 'Jadwal & presensi', 'materials' => 'Panduan & materi', 'assignments' => 'Tugas & laporan', 'progress' => $this->canManage ? 'Peserta & capaian' : 'Capaian saya'] as $key => $label)
            <flux:button wire:click="selectTab('{{ $key }}')" :variant="$tab === $key ? 'primary' : 'ghost'" :aria-current="$tab === $key ? 'page' : null" wire:key="tab-{{ $key }}">{{ $label }}</flux:button>
        @endforeach
    </nav>

    @if ($errors->any())
        <div role="alert" class="rounded-lg bg-red-50 p-4 text-sm text-red-800 dark:bg-red-950 dark:text-red-200"><ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li wire:key="class-error-{{ $loop->index }}">{{ $error }}</li>@endforeach</ul></div>
    @endif

    @if ($tab === 'schedule')
        @include('pages.practicums.schedule')
    @elseif ($tab === 'materials')
        @include('pages.practicums.materials')
    @elseif ($tab === 'assignments')
        @include('pages.practicums.assignments')
    @elseif ($tab === 'progress')
        @include('pages.practicums.progress')
    @else
        <flux:text>Pilih salah satu bagian kelas di atas.</flux:text>
    @endif
</section>
