<?php

namespace App\Actions;

use App\Enums\CertificateStatus;
use App\Models\Certificate;
use App\Models\User;
use App\Services\PortfolioFiles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

class SaveCertificate
{
    public function __construct(private PortfolioFiles $files) {}

    /** @param array<string, mixed> $input */
    public function handle(User $user, ?int $certificateId, array $input, ?UploadedFile $file = null): Certificate
    {
        $newPath = null;
        $oldPath = null;

        try {
            $certificate = DB::transaction(function () use ($user, $certificateId, $input, $file, &$newPath, &$oldPath): Certificate {
                $certificate = $certificateId === null ? new Certificate : Certificate::query()->forUser($user)->lockForUpdate()->findOrFail($certificateId);
                Gate::forUser($user)->authorize($certificate->exists ? 'update' : 'create', $certificate->exists ? $certificate : Certificate::class);

                foreach (['title', 'issuer', 'credential_number'] as $field) {
                    if (is_string($input[$field] ?? null)) {
                        $input[$field] = trim($input[$field]);
                    }
                }

                if (is_array($input['skills'] ?? null)) {
                    $input['skills'] = array_map(fn (mixed $skill): mixed => is_string($skill) ? Str::lower(Str::squish($skill)) : $skill, $input['skills']);
                }

                $data = Validator::make([...$input, 'file' => $file], [
                    'title' => ['required', 'string', 'min:3', 'max:160'],
                    'issuer' => ['required', 'string', 'min:2', 'max:160'],
                    'credential_number' => ['nullable', 'string', 'max:160'],
                    'skills' => ['required', 'array', 'min:1', 'max:12'],
                    'skills.*' => ['required', 'string', 'min:2', 'max:60', 'distinct'],
                    'issued_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
                    'expires_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:issued_on'],
                    'public_consent' => ['accepted'],
                    'file' => [$certificate->exists ? 'nullable' : 'required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
                ], [
                    'title.required' => 'Isi nama sertifikat.',
                    'issuer.required' => 'Isi nama lembaga penerbit.',
                    'skills.required' => 'Tambahkan kompetensi yang tercantum pada sertifikat.',
                    'skills.*.required' => 'Isi setiap kompetensi atau hapus koma yang berlebih.',
                    'skills.*.distinct' => 'Kompetensi yang sama cukup ditulis sekali.',
                    'issued_on.required' => 'Isi tanggal terbit sertifikat.',
                    'issued_on.before_or_equal' => 'Tanggal terbit tidak boleh di masa depan.',
                    'expires_on.after_or_equal' => 'Masa berlaku tidak boleh berakhir sebelum tanggal terbit.',
                    'public_consent.accepted' => 'Setujui informasi yang tampil pada halaman verifikasi sebelum mengajukan.',
                    'file.required' => 'Unggah berkas sertifikat sebelum mengajukan verifikasi.',
                    'file.mimes' => 'Gunakan sertifikat PDF, JPG, atau PNG.',
                    'file.max' => 'Ukuran sertifikat maksimal 10 MB.',
                ])->validate();

                $fromStatus = $certificate->exists ? $certificate->status : null;
                $certificate->fill([
                    'student_id' => $user->id,
                    'holder_name' => $user->name,
                    'title' => $data['title'],
                    'issuer' => $data['issuer'],
                    'credential_number' => ($data['credential_number'] ?? null) ?: null,
                    'skills' => array_values($data['skills']),
                    'issued_on' => $data['issued_on'],
                    'expires_on' => ($data['expires_on'] ?? null) ?: null,
                    'status' => CertificateStatus::Pending,
                    'public_consent_at' => now(),
                ]);

                if ($file !== null) {
                    $stored = $this->files->store($file, 'certificates/'.$user->id, 'file');
                    $newPath = $stored['path'];
                    $oldPath = $certificate->exists ? $certificate->file_path : null;
                    $certificate->fill(['file_path' => $stored['path'], 'file_name' => $stored['name'], 'file_mime' => $stored['mime'], 'file_size' => $stored['size']]);
                }

                $certificate->save();
                $certificate->reviews()->create(['reviewer_id' => $user->id, 'from_status' => $fromStatus, 'to_status' => CertificateStatus::Pending]);

                return $certificate;
            });
        } catch (Throwable $exception) {
            if ($newPath !== null) {
                Storage::disk('local')->delete($newPath);
            }

            throw $exception;
        }

        if ($oldPath !== null) {
            Storage::disk('local')->delete($oldPath);
        }

        return $certificate;
    }

    public function delete(User $user, int $certificateId): void
    {
        $path = DB::transaction(function () use ($user, $certificateId): string {
            $certificate = Certificate::query()->forUser($user)->lockForUpdate()->findOrFail($certificateId);
            Gate::forUser($user)->authorize('delete', $certificate);
            $path = $certificate->file_path;
            $certificate->delete();

            return $path;
        });

        Storage::disk('local')->delete($path);
    }
}
