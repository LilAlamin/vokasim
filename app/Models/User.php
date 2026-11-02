<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property UserRole $role
 * @property string|null $identifier_number
 * @property string|null $study_program
 * @property string|null $institution_or_company
 * @property string|null $phone
 * @property float|null $gpa
 * @property int|null $semester
 * @property int|null $graduation_year
 * @property bool $is_active
 * @property int $auth_version
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'role', 'identifier_number', 'study_program', 'institution_or_company', 'phone', 'gpa', 'semester', 'graduation_year', 'is_active'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'auth_version'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    protected $attributes = ['is_active' => true, 'auth_version' => 1];

    protected static function booted(): void
    {
        static::updating(function (User $user): void {
            if ($user->isDirty(['role', 'is_active']) && $user->getRawOriginal('role') === UserRole::Admin->value && (bool) $user->getRawOriginal('is_active') && (! $user->isAdmin() || ! $user->is_active)) {
                $user->ensureAnotherActiveAdmin();
            }

            if ($user->isDirty('role') && $user->hasRoleAssignments()) {
                throw ValidationException::withMessages(['role' => 'Role belum dapat diubah karena akun masih terhubung dengan penugasan magang atau praktikum.']);
            }
        });

        static::deleting(function (User $user): void {
            if ($user->isAdmin() && $user->is_active) {
                $user->ensureAnotherActiveAdmin();
            }

            if ($user->hasProtectedRecords()) {
                throw ValidationException::withMessages(['account' => 'Akun memiliki riwayat akademik atau penugasan. Nonaktifkan akun untuk mempertahankan riwayatnya.']);
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'gpa' => 'float',
            'semester' => 'integer',
            'graduation_year' => 'integer',
            'is_active' => 'boolean',
            'auth_version' => 'integer',
        ];
    }

    /**
     * Check if user is a student (Mahasiswa).
     */
    public function isMahasiswa(): bool
    {
        return $this->role === UserRole::Mahasiswa;
    }

    /**
     * Check if user is a lecturer / instructor (Dosen).
     */
    public function isDosen(): bool
    {
        return $this->role === UserRole::Dosen;
    }

    /**
     * Check if user is an industry mentor (Mentor DUDI).
     */
    public function isMentorDudi(): bool
    {
        return $this->role === UserRole::MentorDudi;
    }

    /**
     * Check if user is an admin / management.
     */
    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function hasRoleAssignments(): bool
    {
        return InternshipPlacement::query()->where(fn ($query) => $query->where('student_id', $this->id)->orWhere('mentor_id', $this->id)->orWhere('lecturer_id', $this->id))->exists()
            || PracticumModule::query()->where('lecturer_id', $this->id)->exists()
            || PracticumEnrollment::query()->where('student_id', $this->id)->where('status', 'active')->exists();
    }

    public function hasProtectedRecords(): bool
    {
        $references = [
            [InternshipPlacement::class, ['student_id', 'mentor_id', 'lecturer_id']],
            [PracticumModule::class, ['lecturer_id']],
            [PracticumEnrollment::class, ['student_id']],
            [PracticumMaterial::class, ['uploaded_by']],
            [PracticumAttendance::class, ['student_id', 'recorded_by']],
            [PracticumSubmission::class, ['student_id', 'reviewer_id']],
            [PracticumAssessment::class, ['assessor_id']],
            [LogbookReview::class, ['reviewer_id']],
            [IndustryEvaluation::class, ['mentor_id']],
            [InternshipProject::class, ['mentor_id']],
            [InternshipLeaveRequest::class, ['reviewer_id']],
            [PortfolioProject::class, ['student_id']],
            [Certificate::class, ['student_id', 'verified_by', 'revoked_by']],
            [CertificateReview::class, ['reviewer_id']],
            [InterviewSession::class, ['student_id']],
            [InterviewNote::class, ['reviewer_id']],
            [TracerResponse::class, ['user_id']],
        ];

        foreach ($references as [$model, $columns]) {
            if ($model::query()->where(function ($query) use ($columns): void {
                foreach ($columns as $column) {
                    $query->orWhere($column, $this->id);
                }
            })->exists()) {
                return true;
            }
        }

        return false;
    }

    private function ensureAnotherActiveAdmin(): void
    {
        $admins = self::query()->where('role', UserRole::Admin)->where('is_active', true)->orderBy('id')->lockForUpdate()->get(['id']);

        if (! $admins->contains(fn (User $admin): bool => $admin->id !== $this->id)) {
            throw ValidationException::withMessages(['account' => 'Setidaknya satu administrator aktif harus tetap tersedia.']);
        }
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
