<?php

use App\Actions\BuildPartnerAnalytics;
use App\Actions\SaveInternshipPlacement;
use App\Actions\SavePartnerCompany;
use App\Actions\SavePartnerUnit;
use App\Enums\UserRole;
use App\Models\IndustryEvaluation;
use App\Models\InternshipLeaveRequest;
use App\Models\InternshipPlacement;
use App\Models\InternshipProject;
use App\Models\PartnerAgreement;
use App\Models\PartnerCompany;
use App\Models\PartnerUnit;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

test('industry pages redirect guests to sign in', function (string $routeName) {
    $this->get(route($routeName))->assertRedirect(route('login'));
})->with(['partners.index', 'industry.index', 'internships.leaves', 'internships.evaluations', 'tracer.index', 'tracer.survey']);

test('partner management is restricted to administrators', function (UserRole $role, bool $allowed) {
    $user = User::factory()->create(['role' => $role]);

    expect(Gate::forUser($user)->allows('viewAny', PartnerCompany::class))->toBe($allowed);
    expect(Gate::forUser($user)->allows('create', PartnerCompany::class))->toBe($allowed);
})->with([
    'student' => [UserRole::Mahasiswa, false],
    'lecturer' => [UserRole::Dosen, false],
    'mentor' => [UserRole::MentorDudi, false],
    'admin' => [UserRole::Admin, true],
]);

test('the partner page refuses a student', function () {
    $this->actingAs(User::factory()->create())->get(route('partners.index'))->assertForbidden();
});

test('an administrator creates a company and its unit through the editor', function () {
    $admin = User::factory()->admin()->create();
    $input = PartnerCompany::factory()->raw(['name' => 'Mitra Uji Bandung', 'internship_quota' => 8]);

    $page = Livewire::actingAs($admin)->test('pages::partners.index')->call('newCompany')
        ->set('companyForm', $input)->call('saveCompany')->assertHasNoErrors()->assertSee('Mitra Uji Bandung');
    $company = PartnerCompany::query()->sole();
    $page->call('newUnit')->set('unitForm.name', 'Bengkel produksi')
        ->set('unitForm.internship_quota', 4)->call('saveUnit')->assertHasNoErrors();

    $this->assertDatabaseHas('partner_units', ['partner_company_id' => $company->id, 'name' => 'Bengkel produksi', 'internship_quota' => 4, 'radius_meters' => 100]);
});

test('contract uploads stay private and only administrators can download them', function () {
    Storage::fake('local');
    $admin = User::factory()->admin()->create();
    $company = PartnerCompany::factory()->create();
    $input = PartnerAgreement::factory()->for($company, 'company')->raw();

    Livewire::actingAs($admin)->test('pages::partners.index')->call('selectCompany', $company->id)
        ->call('newAgreement')->set('agreementForm', $input)
        ->set('document', UploadedFile::fake()->create('agreement.pdf', 30, 'application/pdf'))
        ->call('saveAgreement')->assertHasNoErrors();
    $agreement = PartnerAgreement::query()->sole();

    Storage::disk('local')->assertExists($agreement->document_path);
    expect($agreement->toArray())->not->toHaveKey('document_path');
    $this->actingAs($admin)->get(route('partners.agreements.download', $agreement))->assertOk()->assertDownload('kerja-sama-'.$agreement->id.'.pdf')->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->actingAs(User::factory()->mentorDudi()->create())->get(route('partners.agreements.download', $agreement))->assertNotFound();
});

test('invalid contract files and reversed dates do not create agreements', function (string $case, string $error) {
    Storage::fake('local');
    $admin = User::factory()->admin()->create();
    $company = PartnerCompany::factory()->create();
    $input = PartnerAgreement::factory()->for($company, 'company')->raw();
    $file = $case === 'wrong file' ? UploadedFile::fake()->create('script.html', 1, 'text/html') : UploadedFile::fake()->create('agreement.pdf', 30, 'application/pdf');
    if ($case === 'reversed dates') {
        $input['expires_on'] = '1990-01-01';
    }

    Livewire::actingAs($admin)->test('pages::partners.index')->call('selectCompany', $company->id)->call('newAgreement')
        ->set('agreementForm', $input)->set('document', $file)->call('saveAgreement')->assertHasErrors([$error]);

    $this->assertDatabaseCount('partner_agreements', 0);
    expect(Storage::disk('local')->allFiles('partner-agreements'))->toBe([]);
})->with(['file type' => ['wrong file', 'document'], 'date order' => ['reversed dates', 'expires_on']]);

test('unit allocations cannot exceed company quota or be moved to another company', function () {
    $admin = User::factory()->admin()->create();
    $company = PartnerCompany::factory()->create(['internship_quota' => 5]);
    $unit = PartnerUnit::factory()->for($company, 'company')->create(['internship_quota' => 4]);
    $other = PartnerCompany::factory()->create();
    $input = PartnerUnit::factory()->for($company, 'company')->raw(['internship_quota' => 2]);

    expect(fn () => app(SavePartnerUnit::class)->handle($admin, $company->id, $input))
        ->toThrow(ValidationException::class, 'Total alokasi unit aktif melebihi kuota perusahaan.');
    Livewire::actingAs($admin)->test('pages::partners.index')->call('selectCompany', $other->id)->call('editUnit', $unit->id)->assertNotFound();

    $this->assertDatabaseCount('partner_units', 1);
});

test('company capacity checks peak occupancy across inclusive placement dates', function () {
    $this->travelTo(now()->setDate(2026, 10, 1));
    $admin = User::factory()->admin()->create();
    $company = PartnerCompany::factory()->create(['internship_quota' => 2]);
    InternshipPlacement::factory()->create(['partner_company_id' => $company->id, 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-15']);
    InternshipPlacement::factory()->create(['partner_company_id' => $company->id, 'starts_on' => '2026-10-16', 'ends_on' => '2026-10-31']);
    $input = InternshipPlacement::factory()->raw(['partner_company_id' => $company->id, 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-31']);

    $placement = app(SaveInternshipPlacement::class)->handle($admin, $input);

    $this->assertModelExists($placement);
    $this->assertDatabaseHas('internship_placements', ['id' => $placement->id, 'partner_company_id' => $company->id, 'company_name' => $company->name]);
    $blocked = InternshipPlacement::factory()->raw(['partner_company_id' => $company->id, 'starts_on' => '2026-10-15', 'ends_on' => '2026-10-16']);
    expect(fn () => app(SaveInternshipPlacement::class)->handle($admin, $blocked))->toThrow(ValidationException::class, 'Kuota perusahaan sudah penuh');
    $this->assertDatabaseCount('internship_placements', 3);
});

test('placement uses the registered unit location and respects its smaller quota', function () {
    $this->freezeTime();
    $admin = User::factory()->admin()->create();
    $company = PartnerCompany::factory()->create(['internship_quota' => 5]);
    $unit = PartnerUnit::factory()->for($company, 'company')->create(['internship_quota' => 1, 'latitude' => -7.25, 'longitude' => 112.75, 'radius_meters' => 50]);
    $input = InternshipPlacement::factory()->raw(['partner_company_id' => $company->id, 'partner_unit_id' => $unit->id]);

    $placement = app(SaveInternshipPlacement::class)->handle($admin, $input);

    $this->assertDatabaseHas('internship_placements', ['id' => $placement->id, 'latitude' => -7.25, 'longitude' => 112.75, 'radius_meters' => 50]);
    $second = InternshipPlacement::factory()->raw(['partner_company_id' => $company->id, 'partner_unit_id' => $unit->id]);
    expect(fn () => app(SaveInternshipPlacement::class)->handle($admin, $second))->toThrow(ValidationException::class, 'Kuota unit kerja sudah penuh');
    $this->assertDatabaseCount('internship_placements', 1);
});

test('placements reject inactive partners and a unit belonging to a different company', function (string $case, string $field) {
    $admin = User::factory()->admin()->create();
    $company = PartnerCompany::factory()->create(['is_active' => $case !== 'inactive']);
    $unit = PartnerUnit::factory()->create();
    $input = InternshipPlacement::factory()->raw(['partner_company_id' => $company->id, 'partner_unit_id' => $case === 'wrong unit' ? $unit->id : null]);

    Livewire::actingAs($admin)->test('pages::internships.placements')->set('form', $input)->call('save')->assertHasErrors([$field]);

    $this->assertDatabaseCount('internship_placements', 0);
})->with(['inactive' => ['inactive', 'partner_company_id'], 'wrong unit' => ['wrong unit', 'partner_unit_id']]);

test('quotas cannot be reduced below reservations already made', function () {
    $this->freezeTime();
    $admin = User::factory()->admin()->create();
    $company = PartnerCompany::factory()->create(['internship_quota' => 2]);
    InternshipPlacement::factory()->count(2)->create(['partner_company_id' => $company->id]);
    $input = $company->getAttributes();
    $input['internship_quota'] = 1;

    expect(fn () => app(SavePartnerCompany::class)->handle($admin, $input, $company->id))->toThrow(ValidationException::class, 'Kuota tidak boleh lebih kecil');

    $this->assertDatabaseHas('partner_companies', ['id' => $company->id, 'internship_quota' => 2]);
});

test('assignments with industry activity cannot be reassigned', function (string $model) {
    $this->freezeTime();
    $placement = InternshipPlacement::factory()->create();
    $model::factory()->for($placement, 'placement')->create();
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)->test('pages::internships.placements')->call('edit', $placement->id)->set('form.radius_meters', 500)->call('save')->assertHasErrors(['placement']);

    $this->assertDatabaseHas('internship_placements', ['id' => $placement->id, 'radius_meters' => 100]);
})->with([InternshipLeaveRequest::class, IndustryEvaluation::class, InternshipProject::class]);

test('partner analytics use active locations current reservations and real expiry dates', function () {
    $this->travelTo(now()->setDate(2026, 10, 31));
    $admin = User::factory()->admin()->create();
    $company = PartnerCompany::factory()->create(['internship_quota' => 5]);
    PartnerCompany::factory()->create(['latitude' => null, 'longitude' => null, 'internship_quota' => 2]);
    PartnerCompany::factory()->inactive()->create(['internship_quota' => 100]);
    InternshipPlacement::factory()->create(['partner_company_id' => $company->id]);
    InternshipPlacement::factory()->create(['partner_company_id' => $company->id, 'starts_on' => '2025-01-01', 'ends_on' => '2025-06-30']);
    PartnerAgreement::factory()->for($company, 'company')->create(['expires_on' => today()->addDays(30)->toDateString()]);
    PartnerAgreement::factory()->for($company, 'company')->expired()->create();
    PartnerAgreement::factory()->for($company, 'company')->expiring()->create(['is_active' => false]);

    $analytics = app(BuildPartnerAnalytics::class)->handle($admin);

    expect($analytics)->toMatchArray(['active_companies' => 2, 'total_quota' => 7, 'current_placements' => 1, 'expiring_agreements' => 1, 'expired_agreements' => 1, 'unmapped_companies' => 1, 'regions' => ['DKI Jakarta' => 2]]);
    expect($analytics['locations'])->toHaveCount(1);
});
