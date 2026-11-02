<?php

namespace App\Actions;

use App\Models\PortfolioProject;
use App\Models\User;
use App\Services\PortfolioFiles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

class SavePortfolioProject
{
    public function __construct(private PortfolioFiles $files) {}

    /** @param array<string, mixed> $input */
    public function handle(User $user, ?int $projectId, array $input, ?UploadedFile $evidence = null): PortfolioProject
    {
        $newPath = null;
        $oldPath = null;

        try {
            $project = DB::transaction(function () use ($user, $projectId, $input, $evidence, &$newPath, &$oldPath): PortfolioProject {
                $project = $projectId === null ? new PortfolioProject : PortfolioProject::query()->forUser($user)->lockForUpdate()->findOrFail($projectId);
                Gate::forUser($user)->authorize($project->exists ? 'update' : 'create', $project->exists ? $project : PortfolioProject::class);

                foreach (['title', 'description', 'project_url'] as $field) {
                    if (is_string($input[$field] ?? null)) {
                        $input[$field] = trim($input[$field]);
                    }
                }

                if (is_array($input['skills'] ?? null)) {
                    $input['skills'] = array_map(fn (mixed $skill): mixed => is_string($skill) ? Str::lower(Str::squish($skill)) : $skill, $input['skills']);
                }

                $data = Validator::make([...$input, 'evidence' => $evidence], [
                    'title' => ['required', 'string', 'min:3', 'max:160'],
                    'description' => ['required', 'string', 'min:20', 'max:10000'],
                    'skills' => ['required', 'array', 'min:1', 'max:12'],
                    'skills.*' => ['required', 'string', 'min:2', 'max:60', 'distinct'],
                    'project_url' => ['nullable', 'url:https,http', 'max:2048'],
                    'completed_on' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
                    'is_published' => ['sometimes', 'boolean'],
                    'remove_evidence' => ['sometimes', 'boolean'],
                    'evidence' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
                ], [
                    'title.required' => 'Isi judul proyek.',
                    'title.min' => 'Judul proyek minimal 3 karakter.',
                    'description.min' => 'Jelaskan proses dan hasil proyek minimal 20 karakter.',
                    'description.required' => 'Jelaskan proses dan hasil proyek.',
                    'skills.required' => 'Tambahkan sedikitnya satu kompetensi.',
                    'skills.*.required' => 'Isi setiap kompetensi atau hapus koma yang berlebih.',
                    'skills.*.distinct' => 'Kompetensi yang sama cukup ditulis sekali.',
                    'project_url.url' => 'Gunakan tautan proyek dengan awalan https:// atau http://.',
                    'completed_on.before_or_equal' => 'Tanggal penyelesaian tidak boleh di masa depan.',
                    'evidence.mimes' => 'Gunakan bukti PDF, JPG, PNG, atau WebP.',
                    'evidence.max' => 'Ukuran bukti maksimal 10 MB.',
                ])->validate();

                $project->fill([
                    'student_id' => $user->id,
                    'title' => $data['title'],
                    'description' => $data['description'],
                    'skills' => array_values($data['skills']),
                    'project_url' => ($data['project_url'] ?? null) ?: null,
                    'completed_on' => ($data['completed_on'] ?? null) ?: null,
                    'is_published' => $data['is_published'] ?? false,
                ]);

                if ($evidence !== null) {
                    $stored = $this->files->store($evidence, 'portfolio-evidence/'.$user->id, 'evidence');
                    $newPath = $stored['path'];
                    $oldPath = $project->evidence_path;
                    $project->fill(['evidence_path' => $stored['path'], 'evidence_name' => $stored['name'], 'evidence_mime' => $stored['mime'], 'evidence_size' => $stored['size']]);
                } elseif ($data['remove_evidence'] ?? false) {
                    $oldPath = $project->evidence_path;
                    $project->fill(['evidence_path' => null, 'evidence_name' => null, 'evidence_mime' => null, 'evidence_size' => null]);
                }

                $project->save();

                return $project;
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

        return $project;
    }

    public function delete(User $user, int $projectId): void
    {
        $path = DB::transaction(function () use ($user, $projectId): ?string {
            $project = PortfolioProject::query()->forUser($user)->lockForUpdate()->findOrFail($projectId);
            Gate::forUser($user)->authorize('delete', $project);
            $path = $project->evidence_path;
            $project->delete();

            return $path;
        });

        if ($path !== null) {
            Storage::disk('local')->delete($path);
        }
    }
}
