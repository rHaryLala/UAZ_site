<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Menu de navigation éditable (handle : header, footer…).
 * Les éléments sont stockés en JSON : [{label, type: page|url, page_id?, url?, new_tab?, children: [...]}].
 */
#[Fillable(['name', 'handle', 'items'])]
class Menu extends Model
{
    protected function casts(): array
    {
        return ['items' => 'array'];
    }

    protected static function booted(): void
    {
        static::saved(fn (Menu $menu) => self::forget($menu->handle));
        static::deleted(fn (Menu $menu) => self::forget($menu->handle));
        // Un changement de chemin de page doit se répercuter dans les menus.
        Page::saved(fn () => Menu::query()->pluck('handle')->each(fn ($h) => self::forget($h)));
    }

    /** Un cache par langue : les adresses sont préfixées (/en, /mg) et les libellés traduits. */
    private static function cacheKey(string $handle, ?string $locale = null): string
    {
        return "menu.$handle.".($locale ?? app()->getLocale());
    }

    private static function forget(string $handle): void
    {
        foreach (array_keys(config('uaz.locales')) as $locale) {
            Cache::forget(self::cacheKey($handle, $locale));
        }
    }

    /**
     * Arbre prêt à afficher : [{label, url, new_tab, children: [{label, url, new_tab}]}].
     * Les pages non publiées sont écartées.
     *
     * @return list<array{label: string, url: string, new_tab: bool, children: list<array<string, mixed>>}>
     */
    public static function resolved(string $handle): array
    {
        return Cache::remember(self::cacheKey($handle), 3600, function () use ($handle) {
            $menu = static::query()->where('handle', $handle)->first();

            if (! $menu) {
                return [];
            }

            $pages = Page::query()->published()
                ->whereIn('id', collect($menu->items)->flatMap(
                    fn ($i) => collect([$i['page_id'] ?? null])->merge(collect($i['children'] ?? [])->pluck('page_id')),
                )->filter()->all())
                ->get()->keyBy('id');

            $resolve = function (array $item) use ($pages, &$resolve): ?array {
                $url = match ($item['type'] ?? 'url') {
                    'page' => ($page = $pages->get($item['page_id'] ?? 0)) ? $page->url() : null,
                    default => $item['url'] ?? null,
                };

                if ($url === null) {
                    return null;
                }

                return [
                    // Les libellés sont du français : le dictionnaire de la langue (lang/{langue}.json) les traduit.
                    'label' => __($item['label']),
                    'url' => str_starts_with($url, '/') ? url($url) : $url,
                    'new_tab' => (bool) ($item['new_tab'] ?? false),
                    'children' => collect($item['children'] ?? [])->map($resolve)->filter()->values()->all(),
                ];
            };

            return collect($menu->items)->map($resolve)->filter()->values()->all();
        });
    }
}
