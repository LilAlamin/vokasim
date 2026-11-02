<?php

namespace App\Enums;

enum UserRole: string
{
    case Mahasiswa = 'mahasiswa';
    case Dosen = 'dosen';
    case MentorDudi = 'mentor_dudi';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Mahasiswa => 'Mahasiswa / Peserta Didik',
            self::Dosen => 'Dosen / Instruktur Praktik',
            self::MentorDudi => 'Mentor Industri (Mitra DUDI)',
            self::Admin => 'Administrator / Manajemen',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Mahasiswa => 'blue',
            self::Dosen => 'emerald',
            self::MentorDudi => 'amber',
            self::Admin => 'purple',
        };
    }
}
