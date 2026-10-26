<?php

namespace App\Models;

use App\Models\Concerns\Publishable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

#[Fillable(['title', 'slug', 'description', 'cover_id', 'taken_on', 'status', 'published_at', 'user_id'])]
class PhotoAlbum extends Model
{
    use HasFactory, HasSlug, Publishable;

    protected function casts(): array
    {
        return ['taken_on' => 'date'];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()->generateSlugsFrom('title')->saveSlugsTo('slug')
            ->slugsShouldBeNoLongerThan(80)
            ->doNotGenerateSlugsOnUpdate()->skipGenerateWhen(fn () => filled($this->slug));
    }

    /** Photos de l'album, dans l'ordre choisi par le rédacteur. */
    public function photos(): BelongsToMany
    {
        return $this->belongsToMany(MediaAsset::class, 'photo_album_media_asset')
            ->withPivot('position')->orderByPivot('position');
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
        return url('/galerie/'.$this->slug);
    }
}
