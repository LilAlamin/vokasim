<?php

namespace App\Enums;

enum NotificationCategory: string
{
    case Practicum = 'practicum';
    case Internship = 'internship';
    case Certificate = 'certificate';
    case Interview = 'interview';
    case Partnership = 'partnership';

    public function label(): string
    {
        return match ($this) {
            self::Practicum => 'Praktikum',
            self::Internship => 'Magang',
            self::Certificate => 'Sertifikat',
            self::Interview => 'Wawancara',
            self::Partnership => 'Kemitraan',
        };
    }
}
