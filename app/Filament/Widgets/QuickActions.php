<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Filament\Resources\NewsPosts\NewsPostResource;
use Filament\Widgets\Widget;

/** Raccourcis vers les tâches les plus fréquentes, selon les droits de la personne connectée. */
class QuickActions extends Widget
{
    protected static ?int $sort = 1;

    protected string $view = 'filament.widgets.quick-actions';

    protected int|string|array $columnSpan = 'full';

    /** @return list<array{label: string, hint: string, icon: string, url: string}> */
    public function getShortcuts(): array
    {
        $shortcuts = [];

        if (NewsPostResource::canCreate()) {
            $shortcuts[] = ['label' => 'Écrire une actualité', 'hint' => 'Annonce, nouvelle du campus', 'icon' => 'heroicon-o-pencil-square', 'url' => NewsPostResource::getUrl('create')];
        }

        if (EventResource::canCreate()) {
            $shortcuts[] = ['label' => 'Ajouter un événement', 'hint' => 'Date, lieu, inscription', 'icon' => 'heroicon-o-calendar-days', 'url' => EventResource::getUrl('create')];
        }

        if (MediaAssetResource::canViewAny()) {
            $shortcuts[] = ['label' => 'Envoyer des images', 'hint' => 'Glissez-déposez dans la médiathèque', 'icon' => 'heroicon-o-arrow-up-tray', 'url' => MediaAssetResource::getUrl()];
        }

        if (ContactMessageResource::canViewAny()) {
            $shortcuts[] = ['label' => 'Lire les messages', 'hint' => 'Boîte de réception du site', 'icon' => 'heroicon-o-envelope', 'url' => ContactMessageResource::getUrl()];
        }

        return $shortcuts;
    }

    public static function canView(): bool
    {
        return auth()->check();
    }
}
