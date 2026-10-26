<?php

namespace App\Filament\Resources\Formations\Pages;

use App\Filament\Resources\Formations\FormationResource;
use App\Filament\Support\PublicationTable;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFormation extends EditRecord
{
    protected static string $resource = FormationResource::class;

    protected function getHeaderActions(): array
    {
        return [PublicationTable::previewAction(), DeleteAction::make()];
    }
}
