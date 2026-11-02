<?php

namespace App\Actions;

use App\Models\PracticumAttendance;
use App\Models\PracticumSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RecordPracticumAttendance
{
    public function checkIn(User $user, int $sessionId): PracticumAttendance
    {
        return DB::transaction(function () use ($user, $sessionId): PracticumAttendance {
            $session = PracticumSession::query()->forUser($user)->with('module')->lockForUpdate()->findOrFail($sessionId);
            Gate::forUser($user)->authorize('participate', $session->module);

            if (! $session->isOpen()) {
                throw ValidationException::withMessages(['attendance' => 'Presensi hanya tersedia selama sesi berlangsung.']);
            }

            if ($session->attendances()->where('student_id', $user->id)->exists()) {
                throw ValidationException::withMessages(['attendance' => 'Presensi sudah tercatat. Hubungi dosen untuk koreksi.']);
            }

            return $session->attendances()->create([
                'student_id' => $user->id, 'recorded_by' => $user->id,
                'status' => now('UTC')->greaterThan($session->starts_at->addMinutes(15)) ? 'late' : 'present',
                'recorded_at' => now('UTC'),
            ]);
        });
    }

    public function handle(User $user, int $sessionId, int $studentId, string $status, ?string $note = null): PracticumAttendance
    {
        return DB::transaction(function () use ($user, $sessionId, $studentId, $status, $note): PracticumAttendance {
            $session = PracticumSession::query()->forUser($user)->with('module')->lockForUpdate()->findOrFail($sessionId);
            Gate::forUser($user)->authorize('manage', $session->module);
            $data = Validator::make(['student_id' => $studentId, 'status' => $status, 'note' => $note], [
                'student_id' => ['required', 'integer', Rule::exists('practicum_enrollments', 'student_id')->where('practicum_module_id', $session->practicum_module_id)->where('status', 'active')],
                'status' => ['required', Rule::in(['present', 'late', 'excused', 'absent'])],
                'note' => ['nullable', 'string', 'max:2000'],
            ], ['student_id.exists' => 'Mahasiswa harus menjadi peserta aktif kelas ini.'])->validate();

            if ($session->cancelled || $session->starts_at->isFuture()) {
                throw ValidationException::withMessages(['attendance' => 'Presensi tidak dapat dicatat untuk sesi mendatang atau dibatalkan.']);
            }

            return $session->attendances()->updateOrCreate(['student_id' => $studentId], [
                'recorded_by' => $user->id, 'status' => $data['status'], 'note' => $data['note'], 'recorded_at' => now('UTC'),
            ]);
        });
    }
}
