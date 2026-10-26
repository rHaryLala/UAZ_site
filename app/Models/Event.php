<?php

namespace App\Models;

use App\Models\Concerns\HasSeo;
use App\Models\Concerns\Publishable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

#[Fillable([
    'title', 'slug', 'summary', 'description', 'starts_at', 'ends_at', 'is_all_day', 'location',
    'location_url', 'registration_url', 'cover_id', 'seo_title', 'seo_description', 'og_image_id',
    'status', 'published_at', 'user_id',
])]
class Event extends Model
{
    use HasFactory, HasSeo, HasSlug, Publishable;

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_all_day' => 'boolean'];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()->generateSlugsFrom('title')->saveSlugsTo('slug')
            ->slugsShouldBeNoLongerThan(80)
            ->doNotGenerateSlugsOnUpdate()->skipGenerateWhen(fn () => filled($this->slug));
    }

    /** @param  Builder<static>  $query */
    public function scopeUpcoming(Builder $query): void
    {
        $query->where(fn ($q) => $q->where('ends_at', '>=', now())->orWhere(
            fn ($q) => $q->whereNull('ends_at')->where('starts_at', '>=', now()->startOfDay()),
        ));
    }

    /** @param  Builder<static>  $query */
    public function scopePast(Builder $query): void
    {
        $query->where(fn ($q) => $q->where('ends_at', '<', now())->orWhere(
            fn ($q) => $q->whereNull('ends_at')->where('starts_at', '<', now()->startOfDay()),
        ));
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
        return url('/evenements/'.$this->slug);
    }
}
