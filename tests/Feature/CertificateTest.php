<?php

use App\Actions\SaveCertificate;
use App\Enums\UserRole;
use App\Models\Certificate;
use App\Models\CertificateReview;
use App\Models\InternshipPlacement;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('submitting a certificate encrypts the file and credential number and creates a pending review', function () {
    Storage::fake('local');
    $this->freezeTime();
    $student = User::factory()->create();
    $contents = "%PDF-1.7\nSertifikat pribadi dengan data peserta.";

    Livewire::actingAs($student)->test('pages::certificates.index')->call('createCertificate')
        ->set('title', 'Pengembangan Aplikasi Web')->set('issuer', 'LSP Politeknik')
        ->set('credentialNumber', 'PRIVAT-12345')->set('skills', 'PHP, SQL')
        ->set('issuedOn', today()->toDateString())->set('publicConsent', true)
        ->set('file', UploadedFile::fake()->createWithContent('sertifikat.pdf', $contents))
        ->call('saveCertificate')->assertHasNoErrors()->assertSet('showEditor', false);

    $certificate = Certificate::query()->sole();
    $this->assertDatabaseHas('certificates', ['id' => $certificate->id, 'student_id' => $student->id, 'status' => 'pending', 'verified_at' => null]);
    $this->assertDatabaseHas('certificate_reviews', ['certificate_id' => $certificate->id, 'reviewer_id' => $student->id, 'from_status' => null, 'to_status' => 'pending']);
    expect($certificate->verification_token)->toBeUuid();
    expect($certificate->holder_name)->toBe($student->name);
    expect($certificate->getRawOriginal('credential_number'))->not->toContain('PRIVAT-12345');
    expect($certificate->credential_number)->toBe('PRIVAT-12345');
    expect($certificate->toArray())->not->toHaveKey('file_path')->not->toHaveKey('credential_number');
    Storage::disk('local')->assertExists($certificate->file_path);
    expect(Storage::disk('local')->get($certificate->file_path))->not->toContain($contents);
    expect(Crypt::decryptString(Storage::disk('local')->get($certificate->file_path)))->toBe($contents);
    $this->get(route('certificates.file', $certificate))->assertOk()->assertDownload('sertifikat-'.$certificate->id.'.pdf')->assertStreamedContent($contents);
    $this->get(route('certificates.qr', $certificate))->assertNotFound();
    Auth::logout();
    $this->get(route('certificates.verify', ['certificate' => $certificate->verification_token]))->assertNotFound();
});

test('an assigned lecturer verifies evidence and a public QR link shows only approved metadata', function () {
    Storage::fake('local');
    $this->freezeTime();
    $placement = InternshipPlacement::factory()->create();
    $certificate = Certificate::factory()->for($placement->student, 'student')->withFile()->create(['holder_name' => $placement->student->name]);

    Livewire::actingAs($placement->lecturer)->test('pages::certificates.index')
        ->call('selectCertificate', $certificate->id)->set('feedback', 'Catatan rahasia pemeriksaan dokumen')
        ->call('review', 'verify')->assertHasNoErrors()->assertSee('QR verifikasi');

    $certificate->refresh();
    $this->assertDatabaseHas('certificates', ['id' => $certificate->id, 'status' => 'verified', 'verified_by' => $placement->lecturer_id]);
    $this->assertDatabaseHas('certificate_reviews', ['certificate_id' => $certificate->id, 'from_status' => 'pending', 'to_status' => 'verified']);
    expect($certificate->verified_at)->not->toBeNull();
    $this->get(route('certificates.qr', $certificate))->assertOk()->assertHeader('Content-Type', 'image/svg+xml')->assertSeeHtml('<svg');
    Auth::logout();

    $this->get(route('certificates.verify', ['certificate' => $certificate->verification_token]))
        ->assertOk()->assertSee('Terverifikasi')->assertSee($certificate->title)->assertSee($certificate->holder_name)
        ->assertDontSee($certificate->credential_number)->assertDontSee($certificate->file_path)->assertDontSee($certificate->file_name)
        ->assertDontSee($placement->student->email)->assertDontSee($placement->student->identifier_number)
        ->assertDontSee($placement->lecturer->name)->assertDontSee('Catatan rahasia pemeriksaan dokumen')
        ->assertViewHas('verification', fn (array $facts): bool => array_keys($facts) === ['holder_name', 'title', 'issuer', 'issued_on', 'expires_on', 'verified_on', 'status', 'is_valid']);
    $this->get(route('certificates.file', $certificate))->assertRedirectToRoute('login');
});

test('revocation preserves the QR address and makes the public status invalid without exposing its reason', function () {
    $this->freezeTime();
    $certificate = Certificate::factory()->verified()->create();
    $token = $certificate->verification_token;
    $administrator = User::factory()->admin()->create();

    Livewire::actingAs($administrator)->test('pages::certificates.index')
        ->call('selectCertificate', $certificate->id)->set('feedback', 'Alasan pencabutan privat dari penerbit')
        ->call('review', 'revoke')->assertHasNoErrors()->assertSee('Dicabut');

    $certificate->refresh();
    $this->assertDatabaseHas('certificates', ['id' => $certificate->id, 'status' => 'revoked', 'revoked_by' => $administrator->id, 'verification_token' => $token]);
    expect($certificate->revoked_at)->not->toBeNull();
    expect(CertificateReview::query()->sole()->getRawOriginal('feedback'))->not->toContain('Alasan pencabutan privat');
    Livewire::actingAs($administrator)->test('pages::certificates.index')->call('selectCertificate', $certificate->id)
        ->call('review', 'verify')->assertForbidden();
    Auth::logout();

    $this->get(route('certificates.verify', ['certificate' => $token]))->assertOk()
        ->assertSee('Dicabut')->assertViewHas('verification', fn (array $facts): bool => $facts['is_valid'] === false)
        ->assertDontSee('Alasan pencabutan privat dari penerbit');
});

test('expired certificates are explicitly invalid on their public verification page', function () {
    $this->freezeTime();
    $certificate = Certificate::factory()->expired()->create();

    $this->get(route('certificates.verify', ['certificate' => $certificate->verification_token]))
        ->assertOk()->assertSee('Kedaluwarsa')->assertViewHas('verification', fn (array $facts): bool => $facts['is_valid'] === false);
});

test('the owner can correct a rejected certificate and replacing the file returns it to pending', function () {
    Storage::fake('local');
    $this->freezeTime();
    $placement = InternshipPlacement::factory()->create();
    $certificate = Certificate::factory()->for($placement->student, 'student')->withFile()->create();
    $oldPath = $certificate->file_path;

    Livewire::actingAs($placement->lecturer)->test('pages::certificates.index')->call('selectCertificate', $certificate->id)
        ->set('feedback', 'Lampirkan halaman lengkap yang mencantumkan kompetensi.')->call('review', 'reject')->assertHasNoErrors();
    Livewire::actingAs($placement->student)->test('pages::certificates.index')->call('editCertificate', $certificate->id)
        ->set('title', 'Sertifikat lengkap')->set('publicConsent', true)
        ->set('file', UploadedFile::fake()->createWithContent('lengkap.pdf', "%PDF-1.7\nDokumen perbaikan lengkap."))
        ->call('saveCertificate')->assertHasNoErrors();

    $certificate->refresh();
    $this->assertDatabaseHas('certificates', ['id' => $certificate->id, 'title' => 'Sertifikat lengkap', 'status' => 'pending', 'verified_at' => null]);
    $this->assertDatabaseHas('certificate_reviews', ['certificate_id' => $certificate->id, 'from_status' => 'rejected', 'to_status' => 'pending']);
    Storage::disk('local')->assertMissing($oldPath);
    Storage::disk('local')->assertExists($certificate->file_path);
});

test('rejection and revocation require a private explanation', function (string $state, string $decision) {
    $certificate = Certificate::factory()->$state()->create();
    $administrator = User::factory()->admin()->create();

    Livewire::actingAs($administrator)->test('pages::certificates.index')->call('selectCertificate', $certificate->id)
        ->call('review', $decision)->assertHasErrors(['feedback'])->assertSee('Tuliskan alasan perbaikan atau pencabutan sertifikat.');

    $this->assertDatabaseHas('certificates', ['id' => $certificate->id, 'status' => $certificate->status->value]);
    $this->assertDatabaseCount('certificate_reviews', 0);
})->with([
    'reject pending evidence' => ['pending', 'reject'],
    'revoke verified evidence' => ['verified', 'revoke'],
]);

test('verified and revoked certificates cannot be changed or deleted by their owner', function (string $state) {
    $certificate = Certificate::factory()->$state()->create();

    Livewire::actingAs($certificate->student)->test('pages::certificates.index')->call('editCertificate', $certificate->id)->assertForbidden();
    Livewire::actingAs($certificate->student)->test('pages::certificates.index')->call('deleteCertificate', $certificate->id)->assertForbidden();
    expect(Gate::forUser($certificate->student)->allows('review', $certificate))->toBeFalse();

    $this->assertModelExists($certificate);
    $this->assertDatabaseHas('certificates', ['id' => $certificate->id, 'status' => $certificate->status->value]);
})->with(['verified', 'revoked']);

test('students and unassigned lecturers cannot view or mutate someone elses certificate', function (UserRole $role) {
    Storage::fake('local');
    $certificate = Certificate::factory()->withFile()->create(['title' => 'Sertifikat privat peserta lain']);
    $viewer = User::factory()->create(['role' => $role]);

    $this->actingAs($viewer)->get(route('certificates.index'))->assertOk()->assertDontSee($certificate->title);
    $this->get(route('certificates.file', $certificate))->assertNotFound();
    $this->get(route('certificates.qr', $certificate))->assertNotFound();
    Livewire::actingAs($viewer)->test('pages::certificates.index')->call('selectCertificate', $certificate->id)->assertNotFound();
    Livewire::actingAs($viewer)->test('pages::certificates.index')->call('editCertificate', $certificate->id)->assertNotFound();

    $this->assertDatabaseHas('certificates', ['id' => $certificate->id, 'status' => 'pending']);
    $this->assertDatabaseCount('certificate_reviews', 0);
})->with([UserRole::Mahasiswa, UserRole::Dosen]);

test('missing or tampered evidence cannot receive verification', function (bool $corrupt, string $message) {
    Storage::fake('local');
    $certificate = Certificate::factory()->create();

    if ($corrupt) {
        Storage::disk('local')->put($certificate->file_path, '%PDF forged unencrypted replacement');
    }

    Livewire::actingAs(User::factory()->admin()->create())->test('pages::certificates.index')
        ->call('selectCertificate', $certificate->id)->call('review', 'verify')
        ->assertHasErrors(['decision'])->assertSee($message);

    $this->assertDatabaseHas('certificates', ['id' => $certificate->id, 'status' => 'pending', 'verified_at' => null]);
    $this->assertDatabaseCount('certificate_reviews', 0);
})->with([
    'missing file' => [false, 'Berkas sertifikat tidak tersedia. Minta mahasiswa mengunggah ulang.'],
    'corrupted ciphertext' => [true, 'Integritas berkas tidak dapat diverifikasi. Minta mahasiswa mengunggah ulang.'],
]);

test('tampered ciphertext fails closed when the owner downloads it', function () {
    config(['app.debug' => false]);
    Storage::fake('local');
    $certificate = Certificate::factory()->create();
    Storage::disk('local')->put($certificate->file_path, 'damaged-private-ciphertext');

    $this->actingAs($certificate->student)->get(route('certificates.file', $certificate))->assertConflict()->assertDontSee('damaged-private-ciphertext');
});

test('a pending submission can be deleted with its encrypted file and review history', function () {
    Storage::fake('local');
    $certificate = Certificate::factory()->withFile()->create();
    CertificateReview::factory()->for($certificate)->create();

    Livewire::actingAs($certificate->student)->test('pages::certificates.index')->call('selectCertificate', $certificate->id)
        ->call('deleteCertificate', $certificate->id)->assertHasNoErrors()->assertSet('selectedId', null);

    $this->assertModelMissing($certificate);
    $this->assertDatabaseMissing('certificate_reviews', ['certificate_id' => $certificate->id]);
    Storage::disk('local')->assertMissing($certificate->file_path);
});

test('a forged status reviewer or owner in the upload payload cannot certify a document', function () {
    Storage::fake('local');
    $this->freezeTime();
    $student = User::factory()->create();
    $other = User::factory()->create();

    $certificate = app(SaveCertificate::class)->handle($student, null, [
        'student_id' => $other->id, 'holder_name' => 'Nama palsu', 'status' => 'verified', 'verified_by' => $student->id,
        'verified_at' => now(), 'verification_token' => '00000000-0000-4000-8000-000000000000',
        'title' => 'Sertifikat Kompetensi', 'issuer' => 'LSP Politeknik', 'skills' => ['php'],
        'issued_on' => today()->toDateString(), 'public_consent' => true,
    ], UploadedFile::fake()->createWithContent('bukti.pdf', "%PDF-1.7\nSertifikat."));

    $this->assertDatabaseHas('certificates', ['id' => $certificate->id, 'student_id' => $student->id, 'holder_name' => $student->name, 'status' => 'pending', 'verified_by' => null, 'verified_at' => null]);
    expect($certificate->verification_token)->toBeUuid()->not->toBe('00000000-0000-4000-8000-000000000000');
});

test('certificate submission explains missing consent required information and file', function () {
    Storage::fake('local');
    $student = User::factory()->create();

    Livewire::actingAs($student)->test('pages::certificates.index')->call('createCertificate')->call('saveCertificate')
        ->assertHasErrors(['title', 'issuer', 'issued_on', 'file', 'public_consent'])
        ->assertSee('Setujui informasi yang tampil pada halaman verifikasi sebelum mengajukan.')
        ->assertSee('Unggah berkas sertifikat sebelum mengajukan verifikasi.');

    $this->assertDatabaseCount('certificates', 0);
    expect(Storage::disk('local')->allFiles('certificates'))->toBe([]);
});

test('HTML renamed to a PDF cannot be submitted as certificate evidence', function () {
    Storage::fake('local');
    $this->freezeTime();
    $student = User::factory()->create();

    Livewire::actingAs($student)->test('pages::certificates.index')->call('createCertificate')
        ->set('title', 'Sertifikat Kompetensi')->set('issuer', 'LSP Politeknik')->set('skills', 'php')
        ->set('issuedOn', today()->toDateString())->set('publicConsent', true)
        ->set('file', UploadedFile::fake()->createWithContent('certificate.pdf', '<html><script>alert(1)</script></html>')->mimeType('text/html'))
        ->call('saveCertificate')->assertHasErrors(['file'])->assertSee('Gunakan sertifikat PDF, JPG, atau PNG.');

    $this->assertDatabaseCount('certificates', 0);
    expect(Storage::disk('local')->allFiles('certificates'))->toBe([]);
});

test('certificate dates reject future issuance and expiry before issuance', function (string $field, string $value, string $error, string $message) {
    Storage::fake('local');
    $this->travelTo(now()->setDate(2026, 10, 31)->startOfDay());
    $student = User::factory()->create();

    Livewire::actingAs($student)->test('pages::certificates.index')->call('createCertificate')
        ->set('title', 'Sertifikat Kompetensi')->set('issuer', 'LSP Politeknik')->set('skills', 'php')
        ->set('issuedOn', '2026-10-30')->set('publicConsent', true)
        ->set('file', UploadedFile::fake()->createWithContent('certificate.pdf', "%PDF-1.7\nSertifikat."))
        ->set($field, $value)->call('saveCertificate')->assertHasErrors([$error])->assertSee($message);

    $this->assertDatabaseCount('certificates', 0);
})->with([
    'future issue' => ['issuedOn', '2026-11-01', 'issued_on', 'Tanggal terbit tidak boleh di masa depan.'],
    'expiry before issue' => ['expiresOn', '2026-10-29', 'expires_on', 'Masa berlaku tidak boleh berakhir sebelum tanggal terbit.'],
]);

test('a lecturer loses certificate access immediately when their assignment changes', function () {
    Storage::fake('local');
    $placement = InternshipPlacement::factory()->create();
    $certificate = Certificate::factory()->for($placement->student, 'student')->withFile()->create();
    $page = Livewire::actingAs($placement->lecturer)->test('pages::certificates.index')->call('selectCertificate', $certificate->id);
    $placement->update(['lecturer_id' => User::factory()->dosen()->create()->id]);

    $page->call('review', 'verify')->assertNotFound();

    $this->assertDatabaseHas('certificates', ['id' => $certificate->id, 'status' => 'pending']);
});

test('public verification escapes text and unavailable tokens do not expose unverified records', function () {
    $script = '<script>alert("certificate")</script>';
    $certificate = Certificate::factory()->verified()->create(['title' => $script, 'holder_name' => $script, 'issuer' => $script]);

    $this->get(route('certificates.verify', ['certificate' => $certificate->verification_token]))->assertOk()->assertSee($script)->assertDontSeeHtml($script);
    $this->get(route('certificates.verify', ['certificate' => '00000000-0000-4000-8000-000000000000']))->assertNotFound();
});

test('certificate management requires a verified account and excludes industry mentors', function () {
    $this->get(route('certificates.index'))->assertRedirectToRoute('login');
    $this->actingAs(User::factory()->unverified()->create())->get(route('certificates.index'))->assertRedirectToRoute('verification.notice');
    $this->actingAs(User::factory()->mentorDudi()->create())->get(route('certificates.index'))->assertForbidden();
});
