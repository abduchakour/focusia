<?php

namespace App\Enums;

enum MaterialType: string
{
    case Course = 'course';
    case CaseStudy = 'case_study';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Course => 'Cours',
            self::CaseStudy => 'Étude de cas',
            self::Other => 'Autre',
        };
    }
}
