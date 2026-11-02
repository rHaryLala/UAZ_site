<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TeamCategory: string implements HasLabel
{
    case Administration = 'administration';
    case Teaching = 'enseignement';

    public function getLabel(): string
    {
        return match ($this) {
            self::Administration => __('Administration'),
            self::Teaching => __('Enseignants'),
        };
    }
}
