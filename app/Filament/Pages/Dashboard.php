<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

/** Tableau de bord : raccourcis, chiffres clés, puis deux listes (brouillons à relire, derniers messages). */
class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Tableau de bord';

    protected static ?string $navigationLabel = 'Tableau de bord';

    public function getColumns(): int|array
    {
        return ['default' => 1, 'xl' => 2];
    }
}
