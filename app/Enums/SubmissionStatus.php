<?php

namespace App\Enums;

/**
 * Per-student state of a homework. Never stored: always derived from the
 * submission, its grade and the due date (see App\Support\SubmissionState).
 */
enum SubmissionStatus: string
{
    case ToSubmit = 'A_RENDRE';
    case Submitted = 'RENDU';
    case Late = 'EN_RETARD';
    case Graded = 'NOTE';
    case Missing = 'NON_RENDU';

    public function label(): string
    {
        return match ($this) {
            self::ToSubmit => 'À rendre',
            self::Submitted => 'Rendu',
            self::Late => 'En retard',
            self::Graded => 'Noté',
            self::Missing => 'Non rendu',
        };
    }
}
