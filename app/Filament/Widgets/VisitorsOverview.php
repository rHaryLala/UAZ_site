<?php

namespace App\Filament\Widgets;

use App\Support\VisitStats;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Visiteurs du site : aujourd'hui, 7 jours, 30 jours, et pages vues (comptés sans cookie ni adresse IP, voir TrackVisit). */
class VisitorsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Visiteurs du site';

    protected ?string $pollingInterval = null;

    protected static bool $isLazy = true;

    protected int|string|array $columnSpan = 'full';

    protected function getDescription(): ?string
    {
        $since = VisitStats::since();

        return $since
            ? 'Visiteurs uniques de chaque jour, comptés sans cookie ni adresse IP depuis le '.CarbonImmutable::parse($since)->locale('fr')->isoFormat('D MMMM YYYY').'. Les robots et le back-office ne comptent pas.'
            : 'Le comptage démarre avec la mise en ligne de cette fonction : les premiers chiffres apparaissent dès les prochaines visites. Aucun cookie, aucune adresse IP conservée.';
    }

    protected function getStats(): array
    {
        $summary = VisitStats::summary();
        $series = array_column(VisitStats::daily(14), 'visitors');
        $diff = $summary['today'] - $summary['yesterday'];
        $perDay = fn (int $total, int $days): string => number_format($total / $days, 1, ',', ' ');

        return [
            Stat::make('Visiteurs aujourd\'hui', number_format($summary['today'], 0, ',', ' '))
                ->description('Hier : '.number_format($summary['yesterday'], 0, ',', ' ').($diff !== 0 ? ' ('.($diff > 0 ? '+' : '').$diff.')' : ''))
                ->descriptionIcon($diff >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($diff >= 0 ? 'success' : 'warning')
                ->chart($series),
            Stat::make('Visiteurs sur 7 jours', number_format($summary['week'], 0, ',', ' '))
                ->description('Soit '.$perDay($summary['week'], 7).' par jour')
                ->icon('heroicon-o-users'),
            Stat::make('Visiteurs sur 30 jours', number_format($summary['month'], 0, ',', ' '))
                ->description('Soit '.$perDay($summary['month'], 30).' par jour')
                ->icon('heroicon-o-user-group'),
            Stat::make('Pages vues sur 30 jours', number_format($summary['views_month'], 0, ',', ' '))
                ->description($summary['month'] > 0 ? number_format($summary['views_month'] / $summary['month'], 1, ',', ' ').' pages par visite' : 'Aucune visite comptée')
                ->icon('heroicon-o-document-text'),
        ];
    }
}
