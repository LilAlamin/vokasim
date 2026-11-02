<?php

namespace App\Actions;

use App\Models\PracticumAssignment;
use App\Models\PracticumSubmission;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class SubmitPracticumReport
{
    public function handle(User $user, int $assignmentId, ?UploadedFile $file, ?string $notes = null): PracticumSubmission
    {
        $path = null;

        try {
            return DB::transaction(function () use ($user, $assignmentId, $file, $notes, &$path): PracticumSubmission {
                $assignment = PracticumAssignment::query()->forUser($user)->with('module')->lockForUpdate()->findOrFail($assignmentId);
                Gate::forUser($user)->authorize('participate', $assignment->module);
                Validator::make(['file' => $file, 'notes' => $notes], [
                    'file' => ['required', 'file', 'mimes:pdf,docx,txt,jpg,jpeg,png', 'extensions:pdf,docx,txt,jpg,jpeg,png', 'max:12288'],
                    'notes' => ['nullable', 'string', 'max:10000'],
                ], ['file.max' => 'Ukuran laporan maksimal 12 MB.', 'file.mimes' => 'Gunakan PDF, DOCX, TXT, JPG, atau PNG.'])->validate();
                $latest = $assignment->submissions()->where('student_id', $user->id)->latest('attempt')->first();

                if ($latest && $latest->status !== 'revision_requested') {
                    throw ValidationException::withMessages(['submission' => 'Laporan sudah dikirim. Kirim ulang hanya setelah dosen meminta revisi.']);
                }

                if (now($assignment->module->timezone)->toDateString() < $assignment->module->starts_on->toDateString()) {
                    throw ValidationException::withMessages(['submission' => 'Pengumpulan dibuka saat periode kelas dimulai.']);
                }

                if (! $latest && ! $assignment->allow_late && $assignment->due_at->isPast()) {
                    throw ValidationException::withMessages(['submission' => 'Tenggat pengumpulan telah lewat. Hubungi dosen untuk membuka pengumpulan terlambat.']);
                }

                if ($assignment->rubricCriteria()->count() < 2) {
                    throw ValidationException::withMessages(['submission' => 'Dosen perlu melengkapi rubrik sebelum laporan dapat dikirim.']);
                }

                $path = $file->store('practicum-submissions/'.$assignment->id.'/'.$user->id, 'local');

                if ($path === false) {
                    throw ValidationException::withMessages(['file' => 'Laporan gagal disimpan. Silakan coba lagi.']);
                }

                return $assignment->submissions()->create([
                    'student_id' => $user->id, 'attempt' => ($latest->attempt ?? 0) + 1,
                    'status' => 'submitted', 'notes' => $notes,
                    'file_path' => $path, 'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                    'mime_type' => $file->getMimeType(), 'submitted_at' => now('UTC'), 'is_late' => $assignment->due_at->isPast(),
                ]);
            });
        } catch (Throwable $exception) {
            if (is_string($path)) {
                Storage::disk('local')->delete($path);
            }

            throw $exception;
        }
    }
}
