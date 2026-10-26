<?php

namespace App\Filament\Support;

use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;

class Layout
{
    /**
     * Colonne principale (2/3) + colonne latérale (1/3) pour publication, SEO, options.
     *
     * @param  list<mixed>  $main
     * @param  list<mixed>  $side
     */
    public static function mainAndSide(array $main, array $side): Grid
    {
        return Grid::make(['default' => 1, 'lg' => 3])
            ->schema([
                Group::make($main)->columnSpan(['lg' => 2]),
                Group::make($side)->columnSpan(['lg' => 1]),
            ])
            ->columnSpanFull();
    }
}
