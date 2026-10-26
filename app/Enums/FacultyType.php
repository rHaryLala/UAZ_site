<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum FacultyType: string implements HasLabel
{
    case Faculty = 'faculte';
    case Department = 'departement';

    public function getLabel(): string
    {
        return match ($this) {
            self::Faculty => 'Faculté',
            self::Department => 'Département',
        };
    }
}
