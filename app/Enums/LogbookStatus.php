<?php

namespace App\Enums;

enum LogbookStatus: string
{
    case Draft = 'draft';
    case PendingMentor = 'pending_mentor';
    case MentorApproved = 'mentor_approved';
    case Verified = 'verified';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingMentor => 'Menunggu Mentor',
            self::MentorApproved => 'Disetujui Mentor',
            self::Verified => 'Terverifikasi Dosen',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'zinc',
            self::PendingMentor => 'amber',
            self::MentorApproved => 'blue',
            self::Verified => 'emerald',
        };
    }
}
