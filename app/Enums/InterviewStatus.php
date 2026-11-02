<?php

namespace App\Enums;

enum InterviewStatus: string
{
    case Preparing = 'preparing';
    case Active = 'active';
    case Evaluating = 'evaluating';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Preparing => 'Menyiapkan pertanyaan',
            self::Active => 'Sedang berlangsung',
            self::Evaluating => 'Menyusun evaluasi',
            self::Completed => 'Selesai',
            self::Failed => 'Perlu dicoba ulang',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Preparing, self::Evaluating => 'amber',
            self::Active => 'blue',
            self::Completed => 'emerald',
            self::Failed => 'red',
            self::Cancelled => 'zinc',
        };
    }
}
