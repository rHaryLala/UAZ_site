<?php

namespace App\Filament\Resources\CafeteriaMenus\Pages;

use App\Filament\Resources\CafeteriaMenus\CafeteriaMenuResource;
use App\Models\Page;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditCafeteriaMenu extends EditRecord
{
    protected static string $resource = CafeteriaMenuResource::class;

    protected static ?string $title = 'Modifier le menu de la semaine';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view')->label('Voir sur le site')->icon(Heroicon::OutlinedArrowTopRightOnSquare)->color('gray')
                ->url(fn () => (Page::where('key', 'student-life-cafeteria')->first()?->url() ?? url('/vie-etudiante/cafeteria')).'#menu')
                ->openUrlInNewTab(),
            DeleteAction::make(),
        ];
    }
}
