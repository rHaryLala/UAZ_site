<?php

namespace App\Models\Concerns;

use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** Champs SEO communs : seo_title, seo_description, og_image_id (avec valeurs de repli). */
trait HasSeo
{
    public function ogImage(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'og_image_id');
    }

    public function metaTitle(): string
    {
        return $this->seo_title ?: (string) ($this->title ?? $this->name ?? '');
    }

    public function metaDescription(): ?string
    {
        if (filled($this->seo_description)) {
            return $this->seo_description;
        }

        $source = $this->summary ?? $this->excerpt ?? $this->description ?? $this->body ?? null;

        return filled($source) ? Str::limit(trim(strip_tags((string) $source)), 160) : null;
    }

    /** Image Open Graph : image dédiée, sinon image de couverture. */
    public function metaImage(): ?MediaAsset
    {
        return $this->ogImage ?? $this->cover ?? null;
    }
}
