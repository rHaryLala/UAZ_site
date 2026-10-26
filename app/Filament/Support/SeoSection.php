<?php

namespace App\Filament\Support;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

class SeoSection
{
    public static function make(): Section
    {
        return Section::make('Référencement (SEO)')
            ->description('Facultatif : sans saisie, le titre et le résumé du contenu sont utilisés.')
            ->collapsed()
            ->schema([
                TextInput::make('seo_title')
                    ->label('Titre pour les moteurs de recherche')
                    ->helperText('60 caractères maximum recommandés.')
                    ->maxLength(255),
                Textarea::make('seo_description')
                    ->label('Description')
                    ->helperText('Environ 150 caractères.')
                    ->rows(2)
                    ->maxLength(320),
                MediaPicker::make('og_image_id', 'Image de partage (réseaux sociaux)'),
            ]);
    }
}
