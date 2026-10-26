<?php

namespace App\Models;

use App\Models\Concerns\HasUazMedia;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Élément de la médiathèque : un fichier (image ou PDF) + titre, texte alternatif, légende, crédit.
 * Tous les contenus référencent ces éléments (couverture, galeries, blocs de pages…).
 */
#[Fillable(['title', 'alt', 'caption', 'credit'])]
class MediaAsset extends Model implements HasMedia
{
    use HasFactory, HasUazMedia;

    public const COLLECTION = 'file';

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::COLLECTION)
            ->singleFile()
            ->acceptsMimeTypes(config('uaz.media.accepted_mime_types'));
    }

    public function file(): ?Media
    {
        return $this->getFirstMedia(self::COLLECTION);
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->file()?->mime_type, 'image/');
    }

    public function isPdf(): bool
    {
        return $this->file()?->mime_type === 'application/pdf';
    }

    public function label(): string
    {
        return $this->title ?: ($this->file()?->file_name ?? 'Média #'.$this->getKey());
    }

    /** URL de l'original ou d'une conversion (thumb, card, hero) ; repli sur l'original. */
    public function url(string $conversion = ''): string
    {
        $media = $this->file();

        if (! $media) {
            return '';
        }

        if ($conversion !== '' && $media->hasGeneratedConversion($conversion)) {
            return $media->getUrl($conversion);
        }

        return $media->getUrl();
    }

    /**
     * Dimensions de l'original (enregistrées à l'envoi).
     *
     * @return array{0: int, 1: int}
     */
    public function dimensions(): array
    {
        $media = $this->file();

        return [(int) $media?->getCustomProperty('width', 0), (int) $media?->getCustomProperty('height', 0)];
    }

    /**
     * Données pour un élément <picture> : sources AVIF/WebP en srcset, src de repli, dimensions.
     *
     * @return array{avif: ?string, webp: ?string, src: string, width: int, height: int}
     */
    public function pictureData(string $conversion = 'card'): array
    {
        $media = $this->file();
        [$width, $height] = $this->dimensions();
        $target = config("uaz.media.conversions.$conversion.width");

        if ($width && $target && $width > $target) {
            $height = (int) round($height * $target / $width);
            $width = $target;
        }

        $srcset = function (string $name) use ($media): ?string {
            if (! $media || ! $media->hasGeneratedConversion($name)) {
                return null;
            }

            return $media->hasResponsiveImages($name) ? $media->getSrcset($name) : $media->getUrl($name);
        };

        return [
            'avif' => $srcset($conversion.'-avif'),
            'webp' => $srcset($conversion),
            'src' => $this->url($conversion),
            'width' => $width,
            'height' => $height,
        ];
    }
}
