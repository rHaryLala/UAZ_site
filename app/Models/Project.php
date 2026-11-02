<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/** Projet de l'UAZ à soutenir (page « Soutenir l'UAZ »). Aucun montant collecté n'est stocké : seulement des informations de présentation. */
#[Fillable(['title', 'slug', 'summary', 'body', 'cover_id', 'location', 'budget', 'status_label', 'url', 'sort_order', 'is_active'])]
class Project extends Model
{
    use HasFactory, HasSlug, HasTranslations;

    /** Attributs dont la traduction vit dans la colonne « translations » (voir HasTranslations). */
    protected array $translatable = ['title', 'summary', 'body', 'location', 'budget', 'status_label'];

    protected array $translatableRich = ['body'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()->generateSlugsFrom('title')->saveSlugsTo('slug')
            ->slugsShouldBeNoLongerThan(80)
            ->doNotGenerateSlugsOnUpdate()->skipGenerateWhen(fn () => filled($this->slug));
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @param  Builder<static>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('title');
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'cover_id');
    }

    public function url(): string
    {
        return url('/soutenir/'.$this->slug);
    }
}
