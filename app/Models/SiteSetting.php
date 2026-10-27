<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/** Réglages globaux du site (une seule ligne). */
#[Fillable([
    'site_name', 'tagline', 'logo_id', 'default_og_image_id', 'address', 'phone', 'email', 'latitude',
    'longitude', 'opening_hours', 'social_links', 'footer_text', 'footer_legal', 'home_hero_title',
    'home_hero_subtitle', 'home_hero_image_id', 'home_cta_image_id','formations_hero_image_id', 'key_figures',
])]
class SiteSetting extends Model
{
    private const CACHE_KEY = 'site-settings';

    protected function casts(): array
    {
        return [
            'opening_hours' => 'array',
            'social_links' => 'array',
            'key_figures' => 'array',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
    }

    /**
     * Ligne unique, créée à la demande. On met en cache les attributs bruts (un tableau) et non le modèle :
     * Laravel interdit par défaut de désérialiser des objets depuis le cache (serializable_classes).
     */
    public static function current(): self
    {
        $attributes = Cache::remember(
            self::CACHE_KEY,
            3600,
            fn () => (static::query()->first() ?? static::create([]))->getAttributes(),
        );

        return (new static)->newFromBuilder($attributes);
    }

    public function logo(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'logo_id');
    }

    public function defaultOgImage(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'default_og_image_id');
    }

    public function homeHeroImage(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'home_hero_image_id');
    }

    public function homeCtaImage(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'home_cta_image_id');
    }

    public function formationsHeroImage(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'formations_hero_image_id');
    }

    public function social(string $network): ?string
    {
        return $this->social_links[$network] ?? null;
    }
}
