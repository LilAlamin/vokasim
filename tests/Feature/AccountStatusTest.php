<?php

use App\Actions\SaveManagedUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

function updateAccountStatus(User $admin, User $user, bool $active): User
{
    $data = [...$user->only(['name', 'email', 'identifier_number', 'study_program', 'institution_or_company', 'gpa', 'semester', 'graduation_year']), 'phone' => '08123456789', 'role' => $user->role->value, 'is_active' => $active];

    return app(SaveManagedUser::class)->handle($admin, $data, $user->id);
}

test('inactive accounts cannot authenticate with a valid password', function () {
    $user = User::factory()->inactive()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors(['email']);
    $this->assertGuest();
});

test('deactivating an authenticated account terminates its next browser request', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertSessionHasNoErrors();
    $this->get(route('practicums.index'))->assertOk();
    updateAccountStatus($admin, $user, false);

    $this->get(route('practicums.index'))->assertRedirect(route('login'))->assertSessionHasErrors(['email']);
    $this->assertGuest();
    $this->assertDatabaseHas('users', ['id' => $user->id, 'is_active' => false, 'auth_version' => 2]);
});

test('deactivation removes only the target accounts stored database sessions', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    config(['session.driver' => 'database']);
    DB::table('sessions')->insert([
        ['id' => 'target-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->timestamp],
        ['id' => 'admin-session', 'user_id' => $admin->id, 'payload' => '', 'last_activity' => now()->timestamp],
    ]);

    updateAccountStatus($admin, $user, false);
    $this->assertDatabaseMissing('sessions', ['id' => 'target-session']);
    $this->assertDatabaseHas('sessions', ['id' => 'admin-session']);
});

test('reactivating an account never restores a session issued before deactivation', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertSessionHas('auth_version', 1);
    $inactive = updateAccountStatus($admin, $user, false);
    $active = updateAccountStatus($admin, $inactive, true);
    expect($active->auth_version)->toBe(3);

    $this->get(route('practicums.index'))->assertRedirect(route('login'));
    $this->assertGuest();
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertSessionHasNoErrors()->assertSessionHas('auth_version', 3);
    $this->get(route('practicums.index'))->assertOk();
});

test('an inactive account cannot complete a previously started two factor challenge', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->withTwoFactor()->create();
    $recoveryCodes = $user->two_factor_recovery_codes;
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('two-factor.login'));
    updateAccountStatus($admin, $user, false);

    $this->post(route('two-factor.login.store'), ['recovery_code' => 'recovery-code-1'])->assertRedirect(route('login'));
    $this->assertGuest();
    expect($user->fresh()->two_factor_recovery_codes)->toBe($recoveryCodes);
});

test('reactivated accounts must restart two factor authentication after pending session revocation', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->withTwoFactor()->create();
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('two-factor.login'));
    $inactive = updateAccountStatus($admin, $user, false);
    updateAccountStatus($admin, $inactive, true);

    $this->post(route('two-factor.login.store'), ['recovery_code' => 'recovery-code-1'])->assertRedirect(route('login'));
    $this->assertGuest();
});

test('Fortify reset tokens work once and invalidate previously issued account sessions', function () {
    Notification::fake();
    $user = User::factory()->create();
    $token = Password::broker()->createToken($user);
    $payload = ['token' => $token, 'email' => $user->email, 'password' => 'Strong-new-password-2026!', 'password_confirmation' => 'Strong-new-password-2026!'];

    $this->post(route('password.update'), $payload)->assertSessionHasNoErrors()->assertRedirect(route('login', absolute: false));
    expect(Hash::check('Strong-new-password-2026!', $user->fresh()->password))->toBeTrue();
    expect($user->fresh()->auth_version)->toBe(2);
    expect(Password::broker()->tokenExists($user, $token))->toBeFalse();
    $this->post(route('password.update'), $payload)->assertSessionHasErrors(['email']);
    Notification::assertNothingSent();
});

test('password reset never reactivates an inactive account', function () {
    Notification::fake();
    $user = User::factory()->inactive()->create();
    $token = Password::broker()->createToken($user);
    $this->post(route('password.update'), ['token' => $token, 'email' => $user->email, 'password' => 'New-password-2026!', 'password_confirmation' => 'New-password-2026!'])->assertSessionHasNoErrors();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'New-password-2026!'])->assertSessionHasErrors(['email']);
    $this->assertGuest();
    expect($user->fresh()->is_active)->toBeFalse();
    Notification::assertNothingSent();
});

test('inactive authenticated users receive an unauthorized response on JSON requests', function () {
    $user = User::factory()->inactive()->create();

    $this->actingAs($user)->getJson(route('practicums.index'))->assertUnauthorized()->assertJson(['message' => 'Akun Anda dinonaktifkan. Hubungi administrator.']);
    $this->assertGuest();
});
