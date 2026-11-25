<?php

namespace App\Enums;

enum Role: string
{
    case Teacher = 'teacher';
    case Student = 'student';

    public function label(): string
    {
        return match ($this) {
            self::Teacher => 'Enseignant',
            self::Student => 'Étudiant',
        };
    }
}
