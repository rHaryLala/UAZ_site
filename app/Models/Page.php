<?php

namespace App\Models;

use App\Models\Concerns\HasSeo;
use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\Publishable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

#[Fillable([
    'parent_id', 'key', 'title', 'slug', 'path', 'summary', 'cover_id', 'blocks',
    'seo_title', 'seo_description', 'og_image_id', 'status', 'published_at', 'sort_order', 'user_id', 'draft',
])]
class Page extends Model
{
    use HasFactory, HasSeo, HasSlug, HasTranslations, Publishable;

    /** Attributs dont la traduction vit dans la colonne « translations » (voir HasTranslations). */
    protected array $translatable = ['title', 'summary', 'blocks', 'seo_title', 'seo_description'];

    /** Chemins de premier niveau réservés aux sections dynamiques ou techniques. */
    public const RESERVED_SLUGS = [
        'admin', 'actualites', 'evenements', 'formations', 'galerie', 'equipe', 'contact',
        'recherche', 'newsletter', 'yearbook', 'design-system', 'storage', 'build', 'livewire', 'sitemap.xml', 'robots.txt', 'up',
        'en', 'mg', // préfixes des langues (config/uaz.php)
    ];

    protected function casts(): array
    {
        return ['blocks' => 'array', 'draft' => 'array'];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('title')
            ->saveSlugsTo('slug')
            ->slugsShouldBeNoLongerThan(80)
            ->doNotGenerateSlugsOnUpdate()
            ->skipGenerateWhen(fn () => filled($this->slug))
            ->extraScope(fn ($query) => $query->where('parent_id', $this->parent_id));
    }

    protected static function booted(): void
    {
        // Après HasSlug (enregistré avant booted()) : le slug existe déjà quand on calcule le chemin.
        $computePath = function (Page $page): void {
            $page->path = $page->computePath();
        };
        static::creating($computePath);
        static::updating($computePath);

        // Les sous-pages suivent le chemin de leur parent.
        static::saved(function (Page $page): void {
            if ($page->wasChanged('path')) {
                $page->children->each(function (Page $child): void {
                    $child->path = $child->computePath();
                    $child->save();
                });
            }
        });
    }

    public function computePath(): string
    {
        $prefix = $this->parent_id ? static::query()->whereKey($this->parent_id)->value('path') : null;

        return trim(($prefix ? $prefix.'/' : '').$this->slug, '/');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('title');
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'cover_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function url(): string
    {
        // « Soutenir l'UAZ » : les liens du site (menus, accueil) vont directement au site des dons s'il est défini.
        if ($this->key === 'support' && filled(config('uaz.donation_url'))) {
            return config('uaz.donation_url');
        }

        return $this->key === 'home' ? url('/') : url('/'.$this->path);
    }

    public static function byKey(string $key): ?self
    {
        return static::query()->where('key', $key)->published()->first();
    }
}
