<?php

namespace App\Actions;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveManagedUser
{
    public function __construct(private RevokeUserSessions $revokeSessions) {}

    /** @param array<string, mixed> $input */
    public function handle(User $actor, array $input, ?int $userId = null): User
    {
        Gate::forUser($actor)->authorize('create', User::class);

        return DB::transaction(function () use ($actor, $input, $userId): User {
            User::query()->where('role', UserRole::Admin)->where('is_active', true)->orderBy('id')->lockForUpdate()->get(['id']);
            $currentActor = User::query()->findOrFail($actor->id);
            Gate::forUser($currentActor)->authorize('create', User::class);
            $user = $userId ? User::query()->lockForUpdate()->findOrFail($userId) : new User;
            $roleInput = $input['role'] ?? null;
            $role = is_string($roleInput) ? UserRole::tryFrom($roleInput) : null;
            $academicRole = in_array($role, [UserRole::Mahasiswa, UserRole::Dosen], true);

            foreach (['identifier_number', 'study_program', 'institution_or_company', 'phone', 'gpa', 'semester', 'graduation_year'] as $field) {
                if (isset($input[$field]) && is_string($input[$field]) && trim($input[$field]) === '') {
                    $input[$field] = null;
                }
            }

            $data = Validator::make($input, [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
                'role' => ['required', 'string', Rule::enum(UserRole::class)],
                'identifier_number' => [$academicRole ? 'required' : 'nullable', 'string', 'max:50'],
                'study_program' => [$academicRole ? 'required' : 'nullable', 'string', 'max:100'],
                'institution_or_company' => [$role === UserRole::MentorDudi ? 'required' : 'nullable', 'string', 'max:150'],
                'phone' => ['nullable', 'string', 'max:25'],
                'gpa' => ['nullable', 'numeric', 'between:0,4', 'decimal:0,2'],
                'semester' => ['nullable', 'integer', 'between:1,14'],
                'graduation_year' => ['nullable', 'integer', 'between:1950,'.(now()->year + 10)],
                'is_active' => ['required', 'boolean'],
            ], ['email.unique' => 'Alamat email sudah digunakan akun lain.', 'gpa.between' => 'IPK harus berada pada rentang 0,00 sampai 4,00.', 'role.enum' => 'Pilih role pengguna yang tersedia.'])->validate();

            if ($role !== UserRole::Mahasiswa) {
                $data['gpa'] = null;
                $data['semester'] = null;
                $data['graduation_year'] = null;
            }

            if (! $user->exists) {
                $user->password = Str::random(64);
            }

            $user->fill($data);
            $sensitiveChange = $user->exists && $user->isDirty(['role', 'email', 'is_active']);

            if ($user->isDirty('email')) {
                if ($user->exists) {
                    $previous = User::query()->findOrFail($user->id);
                    Password::broker(config('fortify.passwords'))->deleteToken($previous);
                }

                $user->email_verified_at = null;
            }

            $user->save();

            if ($sensitiveChange) {
                $this->revokeSessions->handle($user);
            }

            return $user;
        });
    }
}
