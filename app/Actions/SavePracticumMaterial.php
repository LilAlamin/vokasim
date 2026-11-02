<?php

namespace App\Actions;

use App\Models\PracticumMaterial;
use App\Models\PracticumModule;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class SavePracticumMaterial
{
    /** @param array<string, mixed> $input */
    public function handle(User $user, int $moduleId, array $input, ?UploadedFile $file): PracticumMaterial
    {
        $module = PracticumModule::query()->forUser($user)->findOrFail($moduleId);
        Gate::forUser($user)->authorize('manage', $module);
        $data = Validator::make([...$input, 'file' => $file], [
            'title' => ['required', 'string', 'max:255'],
            'kind' => ['required', Rule::in(['k3', 'sop', 'material'])],
            'file' => ['required', 'file', 'mimes:pdf,docx,pptx,xlsx,txt,jpg,jpeg,png', 'extensions:pdf,docx,pptx,xlsx,txt,jpg,jpeg,png', 'max:12288'],
        ], ['file.max' => 'Ukuran materi maksimal 12 MB.', 'file.mimes' => 'Gunakan PDF, dokumen Office, TXT, JPG, atau PNG.'])->validate();
        $path = $file->store('practicum-materials/'.$module->id, 'local');

        if ($path === false) {
            throw ValidationException::withMessages(['file' => 'Materi gagal disimpan. Silakan coba lagi.']);
        }

        try {
            return $module->materials()->create([
                'uploaded_by' => $user->id, 'title' => $data['title'], 'kind' => $data['kind'],
                'file_path' => $path, 'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'mime_type' => $file->getMimeType(), 'size' => $file->getSize(),
            ]);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
    }

    public function remove(User $user, int $materialId): void
    {
        $path = DB::transaction(function () use ($user, $materialId): string {
            $material = PracticumMaterial::query()->whereHas('module', fn ($query) => $query->forUser($user))->lockForUpdate()->findOrFail($materialId);
            Gate::forUser($user)->authorize('delete', $material);
            $path = $material->file_path;
            $material->delete();

            return $path;
        });

        Storage::disk('local')->delete($path);
    }
}
