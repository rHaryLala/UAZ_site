<?php

namespace App\Models\Concerns;

use App\Models\MediaAsset;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Conversions d'images communes à tous les modèles : `thumb`, `card` et `hero`
 * en WebP, plus leurs variantes AVIF (`*-avif`) lorsque GD sait en produire. Les
 * photos de la médiathèque ont en plus `mail` : un JPEG de 1200 px pour les lettres d'information.
 *
 * @mixin HasMedia
 */
trait HasUazMedia
{
    use InteractsWithMedia;

    public function registerMediaConversions(?Media $media = null): void
    {
        if ($media && ! str_starts_with((string) $media->mime_type, 'image/')) {
            return;
        }

        // Les photos de la médiathèque servent aussi aux lettres d'information : Outlook (ordinateur) n'affiche pas le WebP, d'où un JPEG.
        if ($this instanceof MediaAsset) {
            $this->addMediaConversion('mail')->width(1200)->format('jpg')->quality(82)->nonOptimized();
        }

        $config = config('uaz.media');
        $avif = $config['avif'] && function_exists('imageavif');

        foreach ($config['conversions'] as $name => $options) {
            $webp = $this->addMediaConversion($name)
                ->width($options['width'])
                ->format('webp')
                ->quality($options['webp_quality'] ?? $config['webp_quality'])
                ->nonOptimized();

            if ($options['responsive']) {
                $webp->withResponsiveImages();
            }

            if ($avif && ($options['avif'] ?? true)) {
                $conversion = $this->addMediaConversion($name.'-avif')
                    ->width($options['width'])
                    ->format('avif')
                    ->quality($options['avif_quality'] ?? $config['avif_quality'])
                    ->nonOptimized();

                if ($options['responsive']) {
                    $conversion->withResponsiveImages();
                }
            }
        }
    }
}
