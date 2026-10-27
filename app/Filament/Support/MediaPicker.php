<?php

namespace App\Filament\Support;

use App\Filament\Forms\MediaField;

/**
 * Point d'entrée des sélecteurs de média des formulaires. Le champ est un sélecteur « façon WordPress » (App\Filament\Forms\MediaField) :
 * on clique sur « Choisir une image », une fenêtre affiche la médiathèque en grille avec recherche et détails, et permet d'envoyer de
 * nouveaux fichiers (texte alternatif obligatoire).
 */
class MediaPicker
{
    public static function make(string $name, string $label = 'Image', bool $imagesOnly = true): MediaField
    {
        return MediaField::make($name)->label($label)->imagesOnly($imagesOnly);
    }
}
