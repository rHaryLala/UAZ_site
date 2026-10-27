<?php

namespace App\Models\Concerns;

use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Conversions d'images communes à tous les modèles : `thumb`, `card` et `hero`
 * en WebP, plus leurs variantes AVIF (`*-avif`) lorsque GD sait en produire.
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
