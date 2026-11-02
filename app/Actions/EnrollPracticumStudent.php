<?php

namespace App\Actions;

use App\Enums\UserRole;
use App\Models\PracticumEnrollment;
use App\Models\PracticumModule;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class EnrollPracticumStudent
{
    public function handle(User $user, int $moduleId, int $studentId, string $status = 'active'): PracticumEnrollment
    {
        return DB::transaction(function () use ($user, $moduleId, $studentId, $status): PracticumEnrollment {
            $module = PracticumModule::query()->forUser($user)->lockForUpdate()->findOrFail($moduleId);
            Gate::forUser($user)->authorize('manage', $module);
            Validator::make(['student_id' => $studentId, 'status' => $status], [
                'student_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', UserRole::Mahasiswa->value)],
                'status' => ['required', Rule::in(['active', 'withdrawn'])],
            ], ['student_id.exists' => 'Pilih akun mahasiswa yang terdaftar.'])->validate();

            $enrollment = $module->enrollments()->firstOrNew(['student_id' => $studentId]);
            $enrollment->status = $status;
            $enrollment->enrolled_at ??= now('UTC');
            $enrollment->save();

            return $enrollment;
        });
    }
}
