<?php

namespace App\Console\Commands;

use App\Actions\NotifyVokasimReminders;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('vokasim:send-reminders')]
#[Description('Simpan pengingat tenggat praktikum, ujian, dan masa berlaku kerja sama.')]
class SendVokasimReminders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(NotifyVokasimReminders $reminders): int
    {
        $count = $reminders->handle();
        $this->info($count.' notifikasi pengingat baru disimpan.');

        return self::SUCCESS;
    }
}
