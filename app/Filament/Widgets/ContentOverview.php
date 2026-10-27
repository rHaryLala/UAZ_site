<?php

namespace App\Filament\Widgets;

use App\Support\AdminStats;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ContentOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'Aperçu du site';

    // Pas de rafraîchissement automatique (Filament le fait par défaut toutes les 5 s) : les chiffres sont en cache et se
    // mettent à jour quand un contenu change. Chargement différé : le tableau de bord s'affiche sans attendre les chiffres.
    protected ?string $pollingInterval = null;

    protected static bool $isLazy = true;

    protected function getStats(): array
    {
        $stats = AdminStats::get();

        $cards = [
            Stat::make('Actualités publiées', $stats['news_published'])
                ->description($stats['news_drafts'].' brouillon(s)')
                ->icon('heroicon-o-newspaper'),
            Stat::make('Événements à venir', $stats['events_upcoming'])
                ->icon('heroicon-o-calendar-days'),
        ];

        if (auth()->user()?->can('messages.view')) {
            $cards[] = Stat::make('Messages non lus', $stats['messages_unread'])
                ->icon('heroicon-o-envelope')
                ->color($stats['messages_unread'] > 0 ? 'warning' : 'gray');
        }

        return $cards;
    }
}
