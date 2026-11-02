<?php

namespace App\Actions;

use App\Models\InternshipLeaveRequest;
use App\Models\InternshipPlacement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReviewInternshipLeave
{
    public function handle(User $user, int $leaveId, string $decision, string $feedback = ''): InternshipLeaveRequest
    {
        $leave = InternshipLeaveRequest::query()->forUser($user)->with('placement')->findOrFail($leaveId);
        Gate::forUser($user)->authorize('review', $leave);
        $data = Validator::make(['decision' => $decision, 'feedback' => $feedback], [
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'feedback' => ['required_if:decision,rejected', 'nullable', 'string', 'max:2000'],
        ], ['feedback.required_if' => 'Jelaskan alasan penolakan agar mahasiswa dapat menindaklanjuti.'])->validate();

        return DB::transaction(function () use ($user, $leave, $data): InternshipLeaveRequest {
            $placement = InternshipPlacement::query()->forUser($user)->lockForUpdate()->findOrFail($leave->internship_placement_id);
            $leave = $placement->leaveRequests()->lockForUpdate()->findOrFail($leave->id);
            $leave->setRelation('placement', $placement);
            Gate::forUser($user)->authorize('review', $leave);

            if ($leave->status !== 'pending') {
                throw ValidationException::withMessages(['decision' => 'Pengajuan ini sudah diputuskan. Muat ulang daftar untuk melihat keputusan terbaru.']);
            }

            if ($data['decision'] === 'approved' && $placement->attendances()
                ->whereDate('work_date', '>=', $leave->starts_on->toDateString())->whereDate('work_date', '<=', $leave->ends_on->toDateString())->exists()) {
                throw ValidationException::withMessages(['decision' => 'Presensi sudah tercatat pada tanggal izin. Periksa aktivitas lalu tolak pengajuan yang bertentangan.']);
            }

            $leave->update([
                'status' => $data['decision'],
                'feedback' => $data['feedback'] ?: null,
                'reviewer_id' => $user->id,
                'reviewed_at' => now(),
            ]);

            return $leave;
        });
    }
}
