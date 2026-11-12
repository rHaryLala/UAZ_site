<?php

namespace App\Filament\Resources\CafeteriaMenus\Pages;

use App\Filament\Resources\CafeteriaMenus\CafeteriaMenuResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCafeteriaMenus extends ListRecords
{
    protected static string $resource = CafeteriaMenuResource::class;

    protected static ?string $title = 'Menus de la cafétéria';

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nouveau menu')];
    }
}
