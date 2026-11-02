<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Enums\FormationLevel;
use App\Models\Concerns\HasSeo;
use App\Models\Concerns\Publishable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

#[Fillable([
    'faculty_id', 'title', 'slug', 'mention', 'level', 'duration', 'credits', 'summary', 'description',
    'career_prospects', 'admission_requirements', 'cover_id', 'is_featured', 'sort_order',
    'seo_title', 'seo_description', 'og_image_id', 'status', 'published_at', 'user_id',
])]
class Formation extends Model
{
    use HasFactory, HasSeo, HasSlug, Publishable, HasTranslations;

    /** Attributs dont la traduction vit dans la colonne « translations » (voir HasTranslations). */
    protected array $translatable = ['title', 'mention', 'duration', 'summary', 'description', 'career_prospects', 'admission_requirements', 'seo_title', 'seo_description'];

    protected array $translatableRich = ['description', 'career_prospects', 'admission_requirements'];

    protected function casts(): array
    {
        return ['level' => FormationLevel::class, 'is_featured' => 'boolean'];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()->generateSlugsFrom('title')->saveSlugsTo('slug')
            ->slugsShouldBeNoLongerThan(80)
            ->doNotGenerateSlugsOnUpdate()->skipGenerateWhen(fn () => filled($this->slug));
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'cover_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function url(): string
    {
        return url('/formations/'.$this->slug);
    }
}
