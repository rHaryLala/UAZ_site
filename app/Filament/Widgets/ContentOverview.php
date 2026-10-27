<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\NewsPosts\NewsPostResource;
use App\Support\AdminStats;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ContentOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Où en est le site ?';

    // Pas de rafraîchissement automatique (Filament le fait par défaut toutes les 5 s) : les chiffres sont en cache et se
    // mettent à jour quand un contenu change. Chargement différé : le tableau de bord s'affiche sans attendre les chiffres.
    protected ?string $pollingInterval = null;

    protected static bool $isLazy = true;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $stats = AdminStats::get();

        $cards = [
            Stat::make('Actualités publiées', $stats['news_published'])
                ->description('Voir toutes les actualités')
                ->icon('heroicon-o-newspaper')
                ->url(NewsPostResource::getUrl()),
            Stat::make('Brouillons à publier', $stats['news_drafts'])
                ->description($stats['news_drafts'] > 0 ? 'Actualités en attente de publication' : 'Rien en attente')
                ->icon('heroicon-o-pencil-square')
                ->color($stats['news_drafts'] > 0 ? 'warning' : 'gray')
                ->url(NewsPostResource::getUrl('index', ['filters' => ['publication' => ['value' => 'draft']]])),
            Stat::make('Événements à venir', $stats['events_upcoming'])
                ->description('Voir le calendrier')
                ->icon('heroicon-o-calendar-days')
                ->url(EventResource::getUrl('index', ['filters' => ['timing' => ['value' => 'upcoming']]])),
        ];

        if (auth()->user()?->can('messages.view')) {
            $cards[] = Stat::make('Messages non lus', $stats['messages_unread'])
                ->description($stats['messages_unread'] > 0 ? 'À lire et à traiter' : 'Boîte de réception à jour')
                ->icon('heroicon-o-envelope')
                ->color($stats['messages_unread'] > 0 ? 'warning' : 'gray')
                ->url(ContactMessageResource::getUrl());
        }

        return $cards;
    }
}
