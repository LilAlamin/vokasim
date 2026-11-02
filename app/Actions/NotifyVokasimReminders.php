<?php

namespace App\Actions;

use App\Enums\NotificationCategory;
use App\Enums\UserRole;
use App\Models\PartnerAgreement;
use App\Models\PracticumAssignment;
use App\Models\PracticumSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class NotifyVokasimReminders
{
    public function __construct(private NotifyVokasimUser $notify) {}

    public function handle(): int
    {
        return $this->assignments() + $this->exams() + $this->agreements();
    }

    private function assignments(): int
    {
        $created = 0;
        $now = CarbonImmutable::now('UTC');
        $assignments = PracticumAssignment::query()->whereHas('module', fn (Builder $modules) => $modules->where('published', true))
            ->where('due_at', '>', $now)->where('due_at', '<=', $now->addHours((int) config('notifications.reminders.assignment_hours', 24)))
            ->with([
                'module.enrollments' => fn ($enrollments) => $enrollments->where('status', 'active')->with('student'),
                'submissions' => fn ($submissions) => $submissions->latestAttempts()->select(['id', 'practicum_assignment_id', 'student_id', 'status']),
            ])->lazyById(100);

        foreach ($assignments as $assignment) {
            $submissions = $assignment->submissions->keyBy('student_id');

            foreach ($assignment->module->enrollments as $enrollment) {
                if (! $enrollment->student->isMahasiswa() || in_array($submissions->get($enrollment->student_id)?->status, ['submitted', 'graded'], true)) {
                    continue;
                }

                $created += (int) $this->notify->handle($enrollment->student, 'assignment-due:'.$assignment->id.':'.$assignment->due_at->toISOString(), NotificationCategory::Practicum->value,
                    'Tenggat laporan praktikum mendekat', $assignment->title.' dikumpulkan paling lambat '.$assignment->due_at->timezone($assignment->module->timezone)->format('d M Y H:i T').'.',
                    'practicums.assignments.show', ['module' => $assignment->practicum_module_id, 'assignment' => $assignment->id]);
            }
        }

        return $created;
    }

    private function exams(): int
    {
        $created = 0;
        $now = CarbonImmutable::now('UTC');
        $sessions = PracticumSession::query()->where('is_exam', true)->where('cancelled', false)
            ->where('starts_at', '>', $now)->where('starts_at', '<=', $now->addHours((int) config('notifications.reminders.exam_hours', 48)))
            ->whereHas('module', fn (Builder $modules) => $modules->where('published', true))
            ->with(['module.lecturer', 'module.enrollments' => fn ($enrollments) => $enrollments->where('status', 'active')->with('student')])->lazyById(100);

        foreach ($sessions as $session) {
            $message = $session->title.' dijadwalkan '.$session->starts_at->timezone($session->module->timezone)->format('d M Y H:i T').' di '.$session->room.'.';
            $key = 'practicum-exam:'.$session->id.':'.$session->starts_at->toISOString();

            if ($session->module->lecturer->isDosen()) {
                $created += (int) $this->notify->handle($session->module->lecturer, $key, NotificationCategory::Practicum->value, 'Ujian praktik akan berlangsung', $message,
                    'practicums.show', ['module' => $session->practicum_module_id]);
            }

            foreach ($session->module->enrollments as $enrollment) {
                if ($enrollment->student->isMahasiswa()) {
                    $created += (int) $this->notify->handle($enrollment->student, $key, NotificationCategory::Practicum->value, 'Persiapkan ujian praktik', $message,
                        'practicums.show', ['module' => $session->practicum_module_id]);
                }
            }
        }

        return $created;
    }

    private function agreements(): int
    {
        $created = 0;
        $administrators = User::query()->where('role', UserRole::Admin)->get();

        if ($administrators->isEmpty()) {
            return 0;
        }

        $today = CarbonImmutable::today();
        $agreements = PartnerAgreement::query()->where('is_active', true)->whereDate('starts_on', '<=', $today)
            ->whereDate('expires_on', '>=', $today)->whereDate('expires_on', '<=', $today->addDays((int) config('notifications.reminders.agreement_days', 30)))
            ->whereHas('company', fn (Builder $companies) => $companies->where('is_active', true))->with('company')->lazyById(100);

        foreach ($agreements as $agreement) {
            foreach ($administrators as $administrator) {
                $created += (int) $this->notify->handle($administrator, 'agreement-expiry:'.$agreement->id.':'.$agreement->expires_on->toDateString(), NotificationCategory::Partnership->value,
                    'Masa berlaku kerja sama segera berakhir', $agreement->kind.' dengan '.$agreement->company->name.' berakhir pada '.$agreement->expires_on->format('d M Y').'. Tinjau rencana perpanjangan.',
                    'partners.index');
            }
        }

        return $created;
    }
}
