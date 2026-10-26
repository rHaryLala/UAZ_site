<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

#[Fillable(['from_path', 'to_path', 'status_code', 'is_active', 'hits', 'last_hit_at', 'note'])]
class Redirect extends Model
{
    use HasFactory;

    public const CACHE_KEY = 'redirects.map';

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'last_hit_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(function (Redirect $redirect): void {
            $redirect->from_path = static::normalize($redirect->from_path);
        });

        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    /** Chemin canonique : barre initiale, pas de barre finale (sauf la racine), sans domaine ni fragment. */
    public static function normalize(string $path): string
    {
        $path = trim($path);

        if (preg_match('#^https?://[^/]+(/.*)?$#i', $path, $m)) {
            $path = $m[1] ?? '/';
        }

        $path = '/'.ltrim(explode('#', $path)[0], '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    /**
     * Table de redirections actives, indexée par chemin source.
     *
     * @return array<string, array{id: int, to: string, code: int}>
     */
    public static function map(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => static::query()
            ->where('is_active', true)
            ->get(['id', 'from_path', 'to_path', 'status_code'])
            ->mapWithKeys(fn (Redirect $r) => [$r->from_path => ['id' => $r->id, 'to' => $r->to_path, 'code' => $r->status_code]])
            ->all());
    }
}
