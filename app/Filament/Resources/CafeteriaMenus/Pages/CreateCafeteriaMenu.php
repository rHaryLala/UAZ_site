<?php

namespace App\Filament\Resources\CafeteriaMenus\Pages;

use App\Filament\Resources\CafeteriaMenus\CafeteriaMenuResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCafeteriaMenu extends CreateRecord
{
    protected static string $resource = CafeteriaMenuResource::class;

    protected static ?string $title = 'Nouveau menu de la semaine';
}
