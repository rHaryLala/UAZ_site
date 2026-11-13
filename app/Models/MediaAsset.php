<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
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
    use HasFactory, HasTranslations, HasUazMedia;

    /** Attributs dont la traduction vit dans la colonne « translations » (voir HasTranslations). */
    protected array $translatable = ['title', 'alt', 'caption'];

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

    /**
     * Aperçu pour les listes et sélecteurs : la miniature d'une image, ou, pour un PDF, une « page de document » dessinée en SVG
     * (adresse de type data:, sans fichier à générer ni outil externe : le serveur n'a pas à savoir convertir un PDF en image).
     */
    public function previewUrl(string $conversion = 'thumb'): ?string
    {
        if ($this->isPdf()) {
            return $this->pdfPreview();
        }

        return $this->absoluteUrl($conversion);
    }

    private function pdfPreview(): string
    {
        $name = $this->file()?->file_name ?? 'document.pdf';
        $label = e(str($this->title ?: pathinfo($name, PATHINFO_FILENAME))->limit(46, '…')->toString());
        $lines = array_slice(explode("\n", wordwrap($label, 13, "\n", true)), 0, 3);
        $text = collect($lines)->map(fn (string $line, int $i) => '<text x="52" y="'.(188 + $i * 20).'" font-family="Arial,Helvetica,sans-serif" font-size="14" fill="#232E66">'.$line.'</text>')->implode('');
        $size = $this->file()?->human_readable_size;
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 300" width="240" height="300">'
            .'<rect width="240" height="300" fill="#eef1f8"/>'
            .'<path d="M44 20h112l40 40v212a8 8 0 0 1-8 8H44a8 8 0 0 1-8-8V28a8 8 0 0 1 8-8z" fill="#fff" stroke="#c6d2f7" stroke-width="2"/>'
            .'<path d="M156 20v32a8 8 0 0 0 8 8h32z" fill="#dfe6fa"/>'
            .'<rect x="52" y="86" width="64" height="30" rx="4" fill="#c0392b"/>'
            .'<text x="84" y="107" text-anchor="middle" font-family="Arial,Helvetica,sans-serif" font-size="16" font-weight="700" fill="#fff">PDF</text>'
            .'<rect x="52" y="130" width="104" height="6" rx="3" fill="#dfe6fa"/><rect x="52" y="144" width="80" height="6" rx="3" fill="#dfe6fa"/>'
            .$text
            .($size ? '<text x="52" y="262" font-family="Arial,Helvetica,sans-serif" font-size="12" fill="#6371a3">'.e($size).'</text>' : '')
            .'</svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /** Adresse complète (http://…) : requise par les colonnes d'image de Filament, qui traiteraient un chemin relatif comme un chemin du disque. */
    public function absoluteUrl(string $conversion = ''): ?string
    {
        $url = $this->url($conversion);

        return $url === '' ? null : url($url);
    }

    /** URL de l'original ou d'une conversion (thumb, card, hero) ; repli sur l'original. */
    public function url(string $conversion = ''): string
    {
        $media = $this->file();

        if (! $media) {
            return '';
        }

        $conversion = $this->availableConversion($media, $conversion);

        if ($conversion !== '' && $media->hasGeneratedConversion($conversion)) {
            return $media->getUrl($conversion);
        }

        return $media->getUrl();
    }

    /** Conversions de repli tant que la photo n'a pas encore la plus fine (« portrait » → « card ») : jamais l'original en pleine taille. */
    private const FALLBACKS = ['portrait' => 'card'];

    private function availableConversion(?Media $media, string $conversion): string
    {
        while ($media && isset(self::FALLBACKS[$conversion]) && ! $media->hasGeneratedConversion($conversion)) {
            $conversion = self::FALLBACKS[$conversion];
        }

        return $conversion;
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
        $conversion = $this->availableConversion($media, $conversion);
        [$width, $height] = $this->dimensions();
        $target = config("uaz.media.conversions.$conversion.width");

        if ($width && $target && $width > $target) {
            $height = (int) round($height * $target / $width);
            $width = $target;
        }

        // L'AVIF n'est servi que si ce serveur sait en produire : sans cela les conversions « *-avif » ne sont pas déclarées (HasUazMedia) et
        // les demander lèverait une erreur, même si des fichiers AVIF existent (photos envoyées depuis un poste qui les génère).
        $avif = config('uaz.media.avif') && function_exists('imageavif');

        $srcset = function (string $name) use ($media, $avif): ?string {
            if (! $media || (str_ends_with($name, '-avif') && ! $avif) || ! $media->hasGeneratedConversion($name)) {
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
