<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Enums\FacultyType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

#[Fillable(['name', 'slug', 'type', 'summary', 'description', 'cover_id', 'sort_order', 'is_active'])]
class Faculty extends Model
{
    use HasFactory, HasSlug, HasTranslations;

    /** Attributs dont la traduction vit dans la colonne « translations » (voir HasTranslations). */
    protected array $translatable = ['name', 'summary', 'description'];

    protected array $translatableRich = ['description'];

    protected function casts(): array
    {
        return ['type' => FacultyType::class, 'is_active' => 'boolean'];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()->generateSlugsFrom('name')->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()->skipGenerateWhen(fn () => filled($this->slug));
    }

    /** @param  Builder<static>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }

    public function formations(): HasMany
    {
        return $this->hasMany(Formation::class);
    }

    public function teamMembers(): HasMany
    {
        return $this->hasMany(TeamMember::class);
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'cover_id');
    }
}
