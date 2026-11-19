<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

/**
 * Une page vue sur le site public (voir App\Http\Middleware\TrackVisit). Les lignes de plus de 400 jours sont supprimées chaque nuit
 * (php artisan model:prune) : on garde de quoi comparer une année à la précédente, pas davantage.
 */
class SiteVisit extends Model
{
    use Prunable;

    public const KEEP_DAYS = 400;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['visited_at' => 'datetime'];
    }

    public function prunable(): Builder
    {
        return static::query()->where('visited_on', '<', now()->subDays(self::KEEP_DAYS)->toDateString());
    }
}
