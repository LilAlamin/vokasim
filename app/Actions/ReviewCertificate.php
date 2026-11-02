<?php

namespace App\Actions;

use App\Enums\CertificateStatus;
use App\Models\Certificate;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReviewCertificate
{
    public function handle(User $user, int $certificateId, string $decision, ?string $feedback): void
    {
        DB::transaction(function () use ($user, $certificateId, $decision, $feedback): void {
            $certificate = Certificate::query()->forUser($user)->lockForUpdate()->findOrFail($certificateId);
            Gate::forUser($user)->authorize($decision === 'revoke' ? 'revoke' : 'review', $certificate);
            $data = Validator::make(['decision' => $decision, 'feedback' => trim($feedback ?? '')], [
                'decision' => ['required', Rule::in(['verify', 'reject', 'revoke'])],
                'feedback' => ['required_if:decision,reject,revoke', 'nullable', 'string', 'max:2000'],
            ], ['feedback.required_if' => 'Tuliskan alasan perbaikan atau pencabutan sertifikat.'])->validate();

            if ($decision === 'verify') {
                $this->validateEvidence($certificate);
            }

            $fromStatus = $certificate->status;
            $toStatus = match ($data['decision']) {
                'verify' => CertificateStatus::Verified,
                'reject' => CertificateStatus::Rejected,
                'revoke' => CertificateStatus::Revoked,
                default => throw ValidationException::withMessages(['decision' => 'Keputusan pemeriksaan tidak dikenal.']),
            };
            $certificate->status = $toStatus;

            if ($toStatus === CertificateStatus::Verified) {
                $certificate->verified_by = $user->id;
                $certificate->verified_at = now();
            } elseif ($toStatus === CertificateStatus::Revoked) {
                $certificate->revoked_by = $user->id;
                $certificate->revoked_at = now();
            }

            $certificate->save();
            $certificate->reviews()->create(['reviewer_id' => $user->id, 'from_status' => $fromStatus, 'to_status' => $toStatus, 'feedback' => $data['feedback'] ?: null]);
        });
    }

    private function validateEvidence(Certificate $certificate): void
    {
        $encrypted = Storage::disk('local')->get($certificate->file_path);

        if (! is_string($encrypted)) {
            throw ValidationException::withMessages(['decision' => 'Berkas sertifikat tidak tersedia. Minta mahasiswa mengunggah ulang.']);
        }

        try {
            Crypt::decryptString($encrypted);
        } catch (DecryptException) {
            throw ValidationException::withMessages(['decision' => 'Integritas berkas tidak dapat diverifikasi. Minta mahasiswa mengunggah ulang.']);
        }
    }
}
