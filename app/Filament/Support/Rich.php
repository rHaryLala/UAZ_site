<?php

namespace App\Filament\Support;

use Filament\Forms\Components\RichEditor;

/** Éditeur de texte riche simplifié : pas de pièces jointes (les images passent par la médiathèque). */
class Rich
{
    public static function editor(string $name, string $label): RichEditor
    {
        return RichEditor::make($name)
            ->label($label)
            ->toolbarButtons([
                ['bold', 'italic', 'underline', 'link'],
                ['h2', 'h3'],
                ['bulletList', 'orderedList', 'blockquote'],
                ['undo', 'redo'],
            ])
            ->columnSpanFull();
    }
}
