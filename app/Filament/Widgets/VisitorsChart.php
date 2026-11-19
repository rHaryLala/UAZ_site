<?php

namespace App\Filament\Widgets;

use App\Support\VisitStats;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

/** Courbe des visiteurs et des pages vues, jour par jour (7, 30 ou 90 jours). */
class VisitorsChart extends ChartWidget
{
    protected static ?int $sort = 4;

    protected static bool $isLazy = true;

    protected ?string $heading = 'Fréquentation jour par jour';

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '260px';

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 1];

    public ?string $filter = '30';

    protected function getFilters(): ?array
    {
        return ['7' => '7 derniers jours', '30' => '30 derniers jours', '90' => '90 derniers jours'];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $days = in_array($this->filter, ['7', '30', '90'], true) ? (int) $this->filter : 30;
        $daily = VisitStats::daily($days);

        return [
            'datasets' => [
                ['label' => 'Visiteurs', 'data' => array_column($daily, 'visitors'), 'borderColor' => '#0d9488', 'backgroundColor' => 'rgba(13,148,136,.15)', 'fill' => true, 'tension' => 0.3],
                ['label' => 'Pages vues', 'data' => array_column($daily, 'views'), 'borderColor' => '#94a3b8', 'borderDash' => [5, 4], 'tension' => 0.3],
            ],
            'labels' => array_map(fn (string $day) => CarbonImmutable::parse($day)->format('d/m'), array_keys($daily)),
        ];
    }

    protected function getOptions(): array
    {
        return ['scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]], 'plugins' => ['legend' => ['position' => 'bottom']]];
    }
}
