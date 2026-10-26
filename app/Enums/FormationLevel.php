<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum FormationLevel: string implements HasLabel
{
    case Certificate = 'certificat';
    case Licence = 'licence';
    case Master = 'master';
    case Doctorate = 'doctorat';

    public function getLabel(): string
    {
        return match ($this) {
            self::Certificate => 'Certificat',
            self::Licence => 'Licence',
            self::Master => 'Master',
            self::Doctorate => 'Doctorat',
        };
    }
}
