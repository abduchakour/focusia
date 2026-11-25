<?php

namespace App\Enums;

enum ResourceType: string
{
    case Document = 'document';
    case Video = 'video';
    case Link = 'link';
    case Exercise = 'exercise';

    public function label(): string
    {
        return match ($this) {
            self::Document => 'Document',
            self::Video => 'Vidéo',
            self::Link => 'Lien',
            self::Exercise => 'Exercice',
        };
    }
}
