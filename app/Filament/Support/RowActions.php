<?php

namespace App\Filament\Support;

use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;

/** Actions d'une ligne de tableau : « Modifier » et « Supprimer » rangés dans un menu à trois points. */
class RowActions
{
    /** @return list<mixed> */
    public static function editDelete(): array
    {
        return [
            ActionGroup::make([EditAction::make(), DeleteAction::make()])
                ->icon(Heroicon::OutlinedEllipsisVertical)
                ->tooltip('Actions'),
        ];
    }
}
