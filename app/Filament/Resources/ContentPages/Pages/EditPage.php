<?php

namespace App\Filament\Resources\ContentPages\Pages;

use App\Filament\Resources\ContentPages\PageResource;
use App\Filament\Support\PublicationTable;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Éditeur visuel : on modifie la page elle-même (route : admin.visual-editor.show). Hors du mode SPA : il remplace tout l'écran.
            Action::make('visual')
                ->label('Éditeur visuel')
                ->icon(Heroicon::OutlinedPaintBrush)
                ->color('primary')
                ->url(fn (): string => route('admin.visual-editor.show', $this->getRecord())),
            PublicationTable::previewAction(),
            DeleteAction::make(),
        ];
    }
}
