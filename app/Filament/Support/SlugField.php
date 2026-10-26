<?php

namespace App\Filament\Support;

use Filament\Forms\Components\TextInput;
use Illuminate\Support\Str;

class SlugField
{
    /**
     * Champ « Adresse (URL) » : généré depuis le titre à la création si laissé vide, obligatoire ensuite.
     * Passer $unique = false quand l'appelant définit lui-même sa règle d'unicité (ex. pages, par parent).
     */
    public static function make(string $name = 'slug', bool $unique = true): TextInput
    {
        $field = TextInput::make($name)
            ->label('Adresse (URL)')
            ->helperText('Laissez vide pour la générer depuis le titre. Lettres minuscules, chiffres et tirets.')
            ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
            ->maxLength(80)
            ->required(fn (string $operation): bool => $operation === 'edit')
            ->dehydrateStateUsing(fn (?string $state) => filled($state) ? Str::slug($state) : null);

        return $unique ? $field->unique(ignoreRecord: true) : $field;
    }
}
