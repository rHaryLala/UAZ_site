<?php

namespace App\Filament\Widgets;

use App\Support\VisitStats;
use Filament\Widgets\Widget;

/** Les pages les plus vues sur 30 jours, et les sites d'où viennent les visiteurs. */
class TopPages extends Widget
{
    protected static ?int $sort = 5;

    protected static bool $isLazy = true;

    protected string $view = 'filament.widgets.top-pages';

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 1];

    /** @return array{pages: list<array{path: string, views: int, visitors: int}>, referrers: list<array{host: string, views: int}>} */
    protected function getViewData(): array
    {
        return ['pages' => VisitStats::topPages(30, 8), 'referrers' => VisitStats::topReferrers(30, 5)];
    }
}
