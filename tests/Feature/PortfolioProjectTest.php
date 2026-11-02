<?php

use App\Actions\SavePortfolioProject;
use App\Enums\UserRole;
use App\Models\Certificate;
use App\Models\InternshipPlacement;
use App\Models\PortfolioProject;
use App\Models\PracticumEnrollment;
use App\Models\PracticumModule;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('a student creates a private project with normalized skills and encrypted downloadable evidence', function () {
    Storage::fake('local');
    $this->freezeTime();
    $student = User::factory()->create();
    $contents = "%PDF-1.7\nHasil pengukuran komponen privat.";

    Livewire::actingAs($student)->test('pages::portfolios.index')->call('createProject')
        ->set('title', 'Komponen CNC')
        ->set('description', 'Merancang dan mengukur komponen CNC berdasarkan gambar teknik.')
        ->set('skills', 'CNC, Gambar  Teknik, K3')
        ->set('completedOn', today()->toDateString())
        ->set('evidence', UploadedFile::fake()->createWithContent('pengukuran.pdf', $contents))
        ->call('saveProject')->assertHasNoErrors()->assertSet('showEditor', false);

    $project = PortfolioProject::query()->sole();
    $this->assertDatabaseHas('portfolio_projects', ['id' => $project->id, 'student_id' => $student->id, 'is_published' => false]);
    expect($project->skills)->toBe(['cnc', 'gambar teknik', 'k3']);
    expect($project->public_id)->toBeUuid();
    Storage::disk('local')->assertExists($project->evidence_path);
    expect(Storage::disk('local')->get($project->evidence_path))->not->toContain($contents);
    expect(Crypt::decryptString(Storage::disk('local')->get($project->evidence_path)))->toBe($contents);
    $this->actingAs($student)->get(route('portfolios.evidence', $project))->assertOk()
        ->assertDownload('bukti-proyek-'.$project->id.'.pdf')->assertStreamedContent($contents);
});

test('publishing exposes only project facts and unpublishing immediately closes the sharing link', function () {
    Storage::fake('local');
    $project = PortfolioProject::factory()->withEvidence()->create();
    $publicUrl = route('portfolios.show', ['project' => $project->public_id]);
    $this->get($publicUrl)->assertNotFound();

    Livewire::actingAs($project->student)->test('pages::portfolios.index')
        ->call('editProject', $project->id)->set('isPublished', true)->call('saveProject')->assertHasNoErrors();
    Auth::logout();

    $this->get($publicUrl)->assertOk()->assertSee($project->title)
        ->assertDontSee($project->evidence_path)->assertDontSee($project->evidence_name)
        ->assertDontSee($project->student->email)->assertDontSee($project->student->identifier_number)
        ->assertViewHas('evidenceUrl', null);
    $this->get(route('portfolios.evidence', $project))->assertRedirectToRoute('login');

    Livewire::actingAs($project->student)->test('pages::portfolios.index')
        ->call('editProject', $project->id)->set('isPublished', false)->call('saveProject')->assertHasNoErrors();
    Auth::logout();

    $this->get($publicUrl)->assertNotFound();
});

test('replacing and deleting a project removes obsolete private evidence', function () {
    Storage::fake('local');
    $project = PortfolioProject::factory()->withEvidence()->create();
    $oldPath = $project->evidence_path;

    $page = Livewire::actingAs($project->student)->test('pages::portfolios.index')
        ->call('editProject', $project->id)
        ->set('evidence', UploadedFile::fake()->createWithContent('baru.pdf', "%PDF-1.7\nBukti yang diperbarui."))
        ->call('saveProject')->assertHasNoErrors();

    $project->refresh();
    Storage::disk('local')->assertMissing($oldPath);
    Storage::disk('local')->assertExists($project->evidence_path);

    $page->call('deleteProject', $project->id)->assertHasNoErrors();

    $this->assertModelMissing($project);
    Storage::disk('local')->assertMissing($project->evidence_path);
});

test('a student cannot list read edit or delete another students private project', function () {
    Storage::fake('local');
    $student = User::factory()->create();
    $project = PortfolioProject::factory()->withEvidence()->create(['title' => 'Karya privat milik mahasiswa lain']);

    $this->actingAs($student)->get(route('portfolios.index'))->assertOk()->assertDontSee($project->title);
    $this->get(route('portfolios.show', ['project' => $project->public_id]))->assertNotFound();
    $this->get(route('portfolios.evidence', $project))->assertNotFound();
    Livewire::actingAs($student)->test('pages::portfolios.index')->call('editProject', $project->id)->assertNotFound();
    Livewire::actingAs($student)->test('pages::portfolios.index')->call('deleteProject', $project->id)->assertNotFound();

    $this->assertModelExists($project);
    Storage::disk('local')->assertExists($project->evidence_path);
});

test('portfolio and certificate permissions follow ownership and lecturer assignment', function (string $actor, bool $canView, bool $canEdit, bool $canReview) {
    $placement = InternshipPlacement::factory()->create();
    $project = PortfolioProject::factory()->for($placement->student, 'student')->create();
    $certificate = Certificate::factory()->for($placement->student, 'student')->create();
    $viewer = match ($actor) {
        'owner' => $placement->student,
        'lecturer' => $placement->lecturer,
        'mentor' => $placement->mentor,
        'admin' => User::factory()->admin()->create(),
        'other-lecturer' => User::factory()->dosen()->create(),
        'other-student' => User::factory()->create(),
    };

    expect(Gate::forUser($viewer)->allows('view', $project))->toBe($canView);
    expect(Gate::forUser($viewer)->allows('update', $project))->toBe($canEdit);
    expect(Gate::forUser($viewer)->allows('view', $certificate))->toBe($canView);
    expect(Gate::forUser($viewer)->allows('update', $certificate))->toBe($canEdit);
    expect(Gate::forUser($viewer)->allows('review', $certificate))->toBe($canReview);
})->with([
    'student owner' => ['owner', true, true, false],
    'assigned lecturer' => ['lecturer', true, false, true],
    'assigned industry mentor' => ['mentor', false, false, false],
    'administrator' => ['admin', true, false, true],
    'unassigned lecturer' => ['other-lecturer', false, false, false],
    'unrelated student' => ['other-student', false, false, false],
]);

test('an active practicum enrollment grants its lecturer access and removal takes that access away', function () {
    $module = PracticumModule::factory()->create();
    $enrollment = PracticumEnrollment::factory()->for($module, 'module')->create();
    $project = PortfolioProject::factory()->for($enrollment->student, 'student')->create();
    $certificate = Certificate::factory()->for($enrollment->student, 'student')->create();

    $this->actingAs($module->lecturer)->get(route('portfolios.index'))->assertOk()->assertSee($project->title);
    expect(Gate::forUser($module->lecturer)->allows('review', $certificate))->toBeTrue();

    $enrollment->update(['status' => 'inactive']);

    expect(Gate::forUser($module->lecturer)->allows('view', $project))->toBeFalse();
    expect(Gate::forUser($module->lecturer)->allows('review', $certificate))->toBeFalse();
});

test('project ownership and public identifiers cannot be set by an unexpected payload key', function () {
    $student = User::factory()->create();
    $other = User::factory()->create();

    $project = app(SavePortfolioProject::class)->handle($student, null, [
        'student_id' => $other->id, 'public_id' => '00000000-0000-4000-8000-000000000000',
        'title' => 'Proyek mahasiswa', 'description' => 'Membangun sistem sederhana dengan PHP dan pengujian.', 'skills' => ['php'],
    ]);

    $this->assertDatabaseHas('portfolio_projects', ['id' => $project->id, 'student_id' => $student->id, 'is_published' => false]);
    expect($project->public_id)->not->toBe('00000000-0000-4000-8000-000000000000');
});

test('project validation rejects unsafe links duplicate tags and future dates', function (string $field, string $value, string $error, string $message) {
    Storage::fake('local');
    $this->freezeTime();
    $student = User::factory()->create();
    $page = Livewire::actingAs($student)->test('pages::portfolios.index')->call('createProject')
        ->set('title', 'Proyek yang aman')->set('description', 'Uraian proses dan hasil proyek yang dapat dipertanggungjawabkan.')
        ->set('skills', 'php');

    $page->set($field, $value === 'future' ? now()->addDay()->toDateString() : $value)->call('saveProject')
        ->assertHasErrors([$error])->assertSee($message);

    $this->assertDatabaseCount('portfolio_projects', 0);
    expect(Storage::disk('local')->allFiles('portfolio-evidence'))->toBe([]);
})->with([
    'javascript URL' => ['projectUrl', 'javascript:alert(1)', 'project_url', 'Gunakan tautan proyek dengan awalan https:// atau http://.'],
    'duplicate normalized tags' => ['skills', 'PHP, php', 'skills.1', 'Kompetensi yang sama cukup ditulis sekali.'],
    'empty tag' => ['skills', 'php, ', 'skills.1', 'Isi setiap kompetensi atau hapus koma yang berlebih.'],
    'future completion date' => ['completedOn', 'future', 'completed_on', 'Tanggal penyelesaian tidak boleh di masa depan.'],
]);

test('public project descriptions and tags escape user supplied HTML', function () {
    $script = '<script>alert("portfolio")</script>';
    $project = PortfolioProject::factory()->published()->create(['title' => $script, 'description' => $script, 'skills' => [$script]]);

    $this->get(route('portfolios.show', ['project' => $project->public_id]))
        ->assertOk()->assertSee($script)->assertDontSeeHtml($script);
});

test('portfolio pages require sign in and industry mentors cannot open the management page', function () {
    $this->get(route('portfolios.index'))->assertRedirectToRoute('login');
    $this->actingAs(User::factory()->create(['role' => UserRole::MentorDudi]))->get(route('portfolios.index'))->assertForbidden();
});
