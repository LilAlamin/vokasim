<?php

use App\Enums\UserRole;
use App\Models\InternshipPlacement;
use App\Models\PortfolioProject;
use App\Models\PracticumEnrollment;
use App\Models\PracticumModule;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;

function managedUserInput(): array
{
    return ['name' => 'Rani Mahasiswa', 'email' => 'rani.admin-test@example.test', 'role' => 'mahasiswa', 'identifier_number' => 'NIM-2026-001', 'study_program' => 'D4 Teknik Mesin', 'institution_or_company' => 'Politeknik Vokasi', 'phone' => '08123456789', 'gpa' => '3.75', 'semester' => 5, 'graduation_year' => 2027, 'is_active' => true];
}

test('only authenticated administrators can access user management', function () {
    $this->get(route('admin.users'))->assertRedirect(route('login'));
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->get(route('admin.users'))->assertOk()->assertSee('Administrasi pengguna');
});

test('non administrators cannot access the user administration endpoint', function (UserRole $role) {
    $user = User::factory()->create(['role' => $role]);
    $this->actingAs($user)->get(route('admin.users'))->assertForbidden();
})->with([UserRole::Mahasiswa, UserRole::Dosen, UserRole::MentorDudi]);

test('administrator can create academic profiles without accepting injected password or privilege metadata', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    Livewire::actingAs($admin)->test('pages::admin.users')->set('form', [...managedUserInput(), 'password' => 'injected-password', 'email_verified_at' => now(), 'auth_version' => 99])->call('save')->assertHasNoErrors()->assertSee('Rani Mahasiswa');

    $student = User::query()->where('email', 'rani.admin-test@example.test')->sole();
    expect($student->gpa)->toBe(3.75);
    expect($student->semester)->toBe(5);
    expect($student->graduation_year)->toBe(2027);
    expect($student->auth_version)->toBe(1);
    expect($student->email_verified_at)->toBeNull();
    expect(Hash::check('injected-password', $student->password))->toBeFalse();
    Notification::assertNothingSent();
});

test('administrator can create each supported user role', function (UserRole $role) {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)->test('pages::admin.users')->set('form', [...managedUserInput(), 'role' => $role->value])->call('save')->assertHasNoErrors();
    $this->assertDatabaseHas('users', ['email' => 'rani.admin-test@example.test', 'role' => $role->value]);
})->with(UserRole::cases());

test('administrator can clear academic values and profile edits preserve verified email when unchanged', function () {
    $admin = User::factory()->admin()->create();
    $student = User::factory()->create(['gpa' => 3.4, 'semester' => 4, 'graduation_year' => 2027]);

    Livewire::actingAs($admin)->test('pages::admin.users')->call('editUser', $student->id)->set('form.gpa', '')->set('form.semester', '')->set('form.graduation_year', '')->set('form.name', 'Nama Mahasiswa Baru')->call('save')->assertHasNoErrors();
    $student->refresh();
    expect($student->gpa)->toBeNull();
    expect($student->semester)->toBeNull();
    expect($student->graduation_year)->toBeNull();
    expect($student->email_verified_at)->not->toBeNull();
    expect($student->auth_version)->toBe(1);
});

test('email changes revoke verification remember tokens sessions and previous reset links', function () {
    $admin = User::factory()->admin()->create();
    $student = User::factory()->create();
    $oldToken = Password::broker()->createToken($student);
    $remember = $student->remember_token;

    Livewire::actingAs($admin)->test('pages::admin.users')->call('editUser', $student->id)->set('form.email', 'new-address@example.test')->call('save')->assertHasNoErrors();
    expect(Password::broker()->tokenExists($student, $oldToken))->toBeFalse();
    $student->refresh();
    expect($student->email_verified_at)->toBeNull();
    expect($student->auth_version)->toBe(2);
    expect($student->remember_token)->not->toBe($remember);
});

test('admin role and active status changes cannot remove the last active administrator', function () {
    $admin = User::factory()->admin()->create();
    $component = Livewire::actingAs($admin)->test('pages::admin.users')->call('editUser', $admin->id)->set('form.is_active', false)->call('save')->assertHasErrors(['account'])->assertSee('Setidaknya satu administrator aktif harus tetap tersedia.');
    $component->call('editUser', $admin->id)->set('form.role', 'mahasiswa')->call('save')->assertHasErrors(['account']);
    $component->call('deleteUser', $admin->id)->assertHasErrors(['account']);

    $this->assertDatabaseHas('users', ['id' => $admin->id, 'role' => 'admin', 'is_active' => true]);
});

test('an administrator can deactivate another admin when one active administrator remains', function () {
    $actor = User::factory()->admin()->create();
    $target = User::factory()->admin()->create();

    Livewire::actingAs($actor)->test('pages::admin.users')->call('editUser', $target->id)->set('form.is_active', false)->call('save')->assertHasNoErrors();
    $this->assertDatabaseHas('users', ['id' => $target->id, 'is_active' => false, 'auth_version' => 2]);
    $this->assertDatabaseHas('users', ['id' => $actor->id, 'is_active' => true]);
});

test('role changes are blocked while internship or practicum assignments still reference the account', function (string $assignmentType) {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    if ($assignmentType === 'internship') {
        InternshipPlacement::factory()->for($user, 'student')->create();
    } else {
        PracticumEnrollment::factory()->for($user, 'student')->create();
    }

    Livewire::actingAs($admin)->test('pages::admin.users')->call('editUser', $user->id)->set('form.role', 'dosen')->call('save')->assertHasErrors(['role']);
    $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => 'mahasiswa']);
})->with(['internship', 'practicum']);

test('lecturer roles remain protected while they own a class', function () {
    $admin = User::factory()->admin()->create();
    $module = PracticumModule::factory()->create();

    Livewire::actingAs($admin)->test('pages::admin.users')->call('editUser', $module->lecturer_id)->set('form.role', 'mahasiswa')->call('save')->assertHasErrors(['role']);
    $this->assertDatabaseHas('users', ['id' => $module->lecturer_id, 'role' => 'dosen']);
});

test('administrator deletes unused accounts but retains accounts with portfolio history', function () {
    $admin = User::factory()->admin()->create();
    $unused = User::factory()->create();
    $portfolio = PortfolioProject::factory()->create();
    $component = Livewire::actingAs($admin)->test('pages::admin.users')->call('deleteUser', $unused->id)->assertHasNoErrors();
    $this->assertModelMissing($unused);

    $component->call('deleteUser', $portfolio->student_id)->assertHasErrors(['account']);
    $this->assertModelExists($portfolio->student);
    $this->assertModelExists($portfolio);
});

test('last administrator cannot bypass deletion protection through account settings', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)->test('pages::settings.delete-user-modal')->set('password', 'password')->call('deleteUser')->assertHasErrors(['account']);
    $this->assertModelExists($admin);
    $this->assertAuthenticatedAs($admin);
});

test('invalid academic data roles and email conflicts do not create users', function (string $field, mixed $value) {
    $admin = User::factory()->admin()->create(['email' => 'admin-existing@example.test']);

    Livewire::actingAs($admin)->test('pages::admin.users')->set('form', managedUserInput())->set('form.'.$field, $value)->call('save')->assertHasErrors([$field]);
    $this->assertDatabaseCount('users', 1);
})->with(['GPA' => ['gpa', 4.01], 'negative GPA' => ['gpa', -1], 'semester' => ['semester', 0], 'graduation year' => ['graduation_year', 1900], 'role' => ['role', 'root'], 'role shape' => ['role', ['admin']], 'existing email' => ['email', 'admin-existing@example.test'], 'missing identifier' => ['identifier_number', '']]);

test('user administration filters status role program and escapes user content', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->create(['name' => '<script>alert("name")</script>', 'study_program' => 'Teknik Mesin']);
    User::factory()->dosen()->inactive()->create(['name' => 'Dosen Nonaktif', 'study_program' => 'Teknik Informatika']);

    Livewire::actingAs($admin)->test('pages::admin.users')->assertSee('<script>alert("name")</script>')->assertDontSee('<script>alert("name")</script>', false)
        ->set('active', '0')->assertSee('Dosen Nonaktif')->assertDontSee('<script>alert("name")</script>')
        ->set('active', '')->set('role', 'mahasiswa')->assertDontSee('Dosen Nonaktif')
        ->set('role', '')->set('studyProgram', 'Teknik Informatika')->assertSee('Dosen Nonaktif')->assertDontSee('<script>alert("name")</script>');
});

test('admins generate one use password links without sending email and inactive users cannot receive links', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $student = User::factory()->unverified()->create();
    $inactive = User::factory()->inactive()->create();
    $component = Livewire::actingAs($admin)->test('pages::admin.users')->call('createAccessLink', $student->id)->assertHasNoErrors()->assertSet('linkRecipient', $student->email);
    $url = $component->get('accessLink');
    parse_str(parse_url($url, PHP_URL_QUERY), $query);
    $token = basename(parse_url($url, PHP_URL_PATH));

    expect($query['email'])->toBe($student->email);
    expect(Password::broker()->tokenExists($student, $token))->toBeTrue();
    $component->call('createAccessLink', $student->id)->assertHasErrors(['account']);
    $component->call('createAccessLink', $inactive->id)->assertHasErrors(['account']);
    Notification::assertNothingSent();
});
