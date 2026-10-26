<?php

namespace App\Listeners;

use Spatie\Image\Image;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;

/**
 * Compression automatique à l'envoi : l'original est ramené à une largeur raisonnable (2560 px par défaut)
 * et ses dimensions sont mémorisées pour éviter tout décalage de mise en page (CLS) à l'affichage.
 * Exécuté avant la génération des conversions.
 */
class OptimizeUploadedImage
{
    private const RESIZABLE = ['image/jpeg', 'image/png', 'image/webp'];

    public function handle(MediaHasBeenAddedEvent $event): void
    {
        $media = $event->media;

        if (! str_starts_with((string) $media->mime_type, 'image/')) {
            return;
        }

        $path = $media->getPath();

        if (! is_file($path) || ! ($size = @getimagesize($path))) {
            return;
        }

        [$width, $height] = $size;
        $max = (int) config('uaz.media.original_max_width');

        if ($width > $max && in_array($media->mime_type, self::RESIZABLE, true)) {
            Image::load($path)->width($max)->quality(85)->save();
            clearstatcache(true, $path);
            [$width, $height] = getimagesize($path);
            $media->size = filesize($path);
        }

        $media->setCustomProperty('width', $width)->setCustomProperty('height', $height);
        $media->saveQuietly();
    }
}
