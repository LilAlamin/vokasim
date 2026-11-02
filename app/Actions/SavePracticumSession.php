<?php

namespace App\Actions;

use App\Models\PracticumModule;
use App\Models\PracticumSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SavePracticumSession
{
    /** @param array<string, mixed> $input */
    public function handle(User $user, int $moduleId, array $input, ?int $sessionId = null): PracticumSession
    {
        return DB::transaction(function () use ($user, $moduleId, $input, $sessionId): PracticumSession {
            $module = PracticumModule::query()->forUser($user)->lockForUpdate()->findOrFail($moduleId);
            Gate::forUser($user)->authorize('manage', $module);
            $session = $sessionId ? $module->sessions()->findOrFail($sessionId) : new PracticumSession(['practicum_module_id' => $module->id]);
            $data = Validator::make($input, [
                'title' => ['required', 'string', 'max:255'],
                'room' => ['required', 'string', 'max:255'],
                'starts_at' => ['required', 'date_format:Y-m-d\TH:i'],
                'ends_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:starts_at'],
                'is_exam' => ['required', 'boolean'],
                'cancelled' => ['required', 'boolean'],
            ])->validate();
            $startsAt = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data['starts_at'], $module->timezone)->utc();
            $endsAt = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data['ends_at'], $module->timezone)->utc();

            if ($startsAt->setTimezone($module->timezone)->toDateString() < $module->starts_on->toDateString() || $endsAt->setTimezone($module->timezone)->toDateString() > $module->ends_on->toDateString()) {
                throw ValidationException::withMessages(['starts_at' => 'Jadwal harus berada dalam periode kelas.']);
            }

            if ($session->exists && $session->attendances()->exists()) {
                throw ValidationException::withMessages(['session' => 'Sesi yang sudah memiliki presensi tidak dapat diubah atau dibatalkan.']);
            }

            if (! $data['cancelled'] && $module->sessions()->where('cancelled', false)->when($sessionId, fn ($query) => $query->whereKeyNot($sessionId))->where('starts_at', '<', $endsAt)->where('ends_at', '>', $startsAt)->exists()) {
                throw ValidationException::withMessages(['starts_at' => 'Jadwal bertabrakan dengan sesi lain di kelas ini.']);
            }

            $session->fill([...$data, 'starts_at' => $startsAt, 'ends_at' => $endsAt])->save();

            return $session;
        });
    }
}
