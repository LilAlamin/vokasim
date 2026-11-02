<?php

namespace App\Actions;

use App\Enums\UserRole;
use App\Models\PracticumModule;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SavePracticumModule
{
    /** @param array<string, mixed> $input */
    public function handle(User $user, array $input, ?int $moduleId = null): PracticumModule
    {
        Gate::forUser($user)->authorize('create', PracticumModule::class);

        return DB::transaction(function () use ($user, $input, $moduleId): PracticumModule {
            $module = $moduleId ? PracticumModule::query()->forUser($user)->lockForUpdate()->findOrFail($moduleId) : new PracticumModule;

            if ($module->exists) {
                Gate::forUser($user)->authorize('manage', $module);
            }

            $input['lecturer_id'] = $user->isDosen() ? $user->id : ($input['lecturer_id'] ?? null);
            $data = Validator::make($input, [
                'lecturer_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', UserRole::Dosen->value)],
                'code' => ['required', 'string', 'max:80', Rule::unique('practicum_modules', 'code')->ignore($module->id)],
                'title' => ['required', 'string', 'max:255'],
                'study_program' => ['required', 'string', 'max:255'],
                'semester' => ['required', 'integer', 'between:1,14'],
                'skill_area' => ['required', 'string', 'max:255'],
                'description' => ['required', 'string', 'max:10000'],
                'timezone' => ['required', Rule::in(['Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura'])],
                'starts_on' => ['required', 'date_format:Y-m-d'],
                'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
                'published' => ['required', 'boolean'],
            ], ['code.unique' => 'Kode kelas sudah digunakan. Gunakan kode kelas dan periode yang berbeda.'])->validate();

            if ($module->exists && $module->sessions()->get()->contains(fn ($session): bool => $session->starts_at->setTimezone($data['timezone'])->toDateString() < $data['starts_on'] || $session->ends_at->setTimezone($data['timezone'])->toDateString() > $data['ends_on'])) {
                throw ValidationException::withMessages(['starts_on' => 'Periode kelas harus mencakup semua sesi yang sudah dijadwalkan.']);
            }

            $module->fill($data)->save();

            return $module;
        });
    }
}
