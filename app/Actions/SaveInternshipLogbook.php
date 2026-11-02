<?php

namespace App\Actions;

use App\Enums\LogbookStatus;
use App\Models\InternshipLogbook;
use App\Models\InternshipPlacement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class SaveInternshipLogbook
{
    /** @param array<string, mixed> $input */
    public function handle(User $user, int $placementId, array $input, ?UploadedFile $photo = null): InternshipLogbook
    {
        $newPath = null;
        $oldPath = null;

        try {
            $logbook = DB::transaction(function () use ($user, $placementId, $input, $photo, &$newPath, &$oldPath): InternshipLogbook {
                $placement = InternshipPlacement::query()->forUser($user)->lockForUpdate()->findOrFail($placementId);
                Gate::forUser($user)->authorize('attend', $placement);
                $data = Validator::make([...$input, 'photo' => $photo], [
                    'work_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$placement->starts_on->toDateString(), 'before_or_equal:'.$placement->ends_on->toDateString(), 'before_or_equal:'.CarbonImmutable::now($placement->timezone)->toDateString()],
                    'description' => ['nullable', 'string', 'max:10000'],
                    'effective_hours' => ['required', 'numeric', 'between:0,24', 'multiple_of:0.25'],
                    'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=8000,max_height=8000'],
                ], [
                    'work_date.after_or_equal' => 'Tanggal aktivitas harus berada dalam periode magang.',
                    'work_date.before_or_equal' => 'Tanggal aktivitas harus dalam periode magang dan tidak boleh di masa depan.',
                    'effective_hours.multiple_of' => 'Isi jam kerja dalam kelipatan 0,25 jam (15 menit).',
                    'photo.max' => 'Ukuran foto maksimal 5 MB.',
                    'photo.mimes' => 'Gunakan foto JPG, PNG, atau WebP.',
                ])->validate();

                $logbook = $placement->logbooks()->whereDate('work_date', $data['work_date'])->lockForUpdate()->first();

                if ($logbook) {
                    Gate::forUser($user)->authorize('update', $logbook);
                } else {
                    $logbook = new InternshipLogbook(['internship_placement_id' => $placement->id, 'work_date' => $data['work_date'], 'status' => LogbookStatus::Draft]);
                }

                if ($photo) {
                    $newPath = $photo->store('internship-logbooks/'.$placement->id, 'local');

                    if ($newPath === false) {
                        throw ValidationException::withMessages(['photo' => 'Foto gagal disimpan. Silakan coba lagi.']);
                    }

                    $oldPath = $logbook->photo_path;
                    $logbook->photo_path = $newPath;
                }

                $logbook->description = trim((string) ($data['description'] ?? ''));
                $logbook->effective_minutes = (int) round((float) $data['effective_hours'] * 60);
                $logbook->save();

                return $logbook;
            });
        } catch (Throwable $exception) {
            if (is_string($newPath)) {
                Storage::disk('local')->delete($newPath);
            }

            throw $exception;
        }

        if ($oldPath) {
            Storage::disk('local')->delete($oldPath);
        }

        return $logbook;
    }

    public function submit(User $user, int $logbookId): void
    {
        DB::transaction(function () use ($user, $logbookId): void {
            $logbook = InternshipLogbook::query()->forUser($user)->lockForUpdate()->findOrFail($logbookId);
            Gate::forUser($user)->authorize('update', $logbook);
            $attendance = $logbook->placement->attendances()->whereDate('work_date', $logbook->work_date->toDateString())->first();

            if (! $attendance?->checked_out_at) {
                throw ValidationException::withMessages(['logbook' => 'Lengkapi presensi datang dan pulang untuk tanggal ini sebelum mengirim logbook.']);
            }

            if (mb_strlen(trim($logbook->description)) < 20) {
                throw ValidationException::withMessages(['description' => 'Jelaskan aktivitas teknis minimal 20 karakter sebelum mengirim.']);
            }

            if ($logbook->effective_minutes < 15 || $logbook->effective_minutes > $attendance->elapsedMinutes()) {
                throw ValidationException::withMessages(['effective_hours' => 'Jam kerja efektif minimal 0,25 jam dan tidak boleh melebihi durasi presensi ('.number_format($attendance->elapsedMinutes() / 60, 2).' jam).']);
            }

            if (! $logbook->photo_path || ! Storage::disk('local')->exists($logbook->photo_path)) {
                throw ValidationException::withMessages(['photo' => 'Unggah foto dokumentasi sebelum mengirim logbook.']);
            }

            $logbook->update(['status' => LogbookStatus::PendingMentor, 'submitted_at' => CarbonImmutable::now('UTC'), 'mentor_approved_at' => null, 'verified_at' => null]);
            $logbook->reviews()->create(['reviewer_id' => $user->id, 'from_status' => LogbookStatus::Draft, 'to_status' => LogbookStatus::PendingMentor]);
        });
    }
}
