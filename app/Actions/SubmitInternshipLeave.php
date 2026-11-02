<?php

namespace App\Actions;

use App\Models\InternshipLeaveRequest;
use App\Models\InternshipPlacement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class SubmitInternshipLeave
{
    /** @param array<string, mixed> $input */
    public function handle(User $user, int $placementId, array $input, ?UploadedFile $evidence = null): InternshipLeaveRequest
    {
        $storedPath = null;

        try {
            return DB::transaction(function () use ($user, $placementId, $input, $evidence, &$storedPath): InternshipLeaveRequest {
                $placement = InternshipPlacement::query()->forUser($user)->lockForUpdate()->findOrFail($placementId);
                Gate::forUser($user)->authorize('create', [InternshipLeaveRequest::class, $placement]);
                $data = Validator::make([...$input, 'evidence' => $evidence], [
                    'kind' => ['required', Rule::in(['leave', 'sick'])],
                    'starts_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$placement->starts_on->toDateString()],
                    'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on', 'before_or_equal:'.$placement->ends_on->toDateString()],
                    'reason' => ['required', 'string', 'min:10', 'max:2000'],
                    'evidence' => ['required_if:kind,sick', 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'extensions:pdf,jpg,jpeg,png', 'max:5120'],
                ], [
                    'reason.min' => 'Jelaskan alasan izin atau sakit setidaknya 10 karakter.',
                    'evidence.required_if' => 'Unggah surat atau bukti untuk pengajuan sakit.',
                    'evidence.mimes' => 'Bukti harus berupa PDF, JPG, atau PNG.',
                    'evidence.max' => 'Ukuran bukti maksimal 5 MB.',
                    'starts_on.after_or_equal' => 'Tanggal izin harus berada dalam periode magang.',
                    'ends_on.before_or_equal' => 'Tanggal izin harus berada dalam periode magang.',
                ])->validate();

                if (CarbonImmutable::parse($data['starts_on'])->diffInDays(CarbonImmutable::parse($data['ends_on'])) > 30) {
                    throw ValidationException::withMessages(['ends_on' => 'Satu pengajuan maksimal 31 hari. Hubungi pembimbing untuk izin lebih panjang.']);
                }

                if ($placement->leaveRequests()->whereIn('status', ['pending', 'approved'])
                    ->whereDate('starts_on', '<=', $data['ends_on'])->whereDate('ends_on', '>=', $data['starts_on'])->exists()) {
                    throw ValidationException::withMessages(['starts_on' => 'Sudah ada pengajuan menunggu atau disetujui pada tanggal tersebut.']);
                }

                if ($placement->attendances()->whereDate('work_date', '>=', $data['starts_on'])->whereDate('work_date', '<=', $data['ends_on'])->exists()) {
                    throw ValidationException::withMessages(['starts_on' => 'Presensi sudah tercatat pada rentang ini. Izin sehari penuh tidak dapat menggantikan presensi.']);
                }

                unset($data['evidence']);

                if ($evidence) {
                    $storedPath = $evidence->store('internship-leaves/'.$placement->id, 'local');

                    if (! is_string($storedPath)) {
                        throw ValidationException::withMessages(['evidence' => 'Bukti gagal disimpan. Coba unggah kembali.']);
                    }
                }

                return $placement->leaveRequests()->create([...$data, 'evidence_path' => $storedPath, 'status' => 'pending']);
            });
        } catch (Throwable $exception) {
            if (is_string($storedPath)) {
                Storage::disk('local')->delete($storedPath);
            }

            throw $exception;
        }
    }
}
