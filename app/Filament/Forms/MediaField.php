<?php

namespace App\Filament\Forms;

use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Models\MediaAsset;
use Filament\Forms\Components\Field;

/**
 * Sélecteur de médias façon WordPress : fenêtre avec la médiathèque en grille, recherche, détails du fichier et envoi de fichiers.
 * Mode simple (un identifiant) ou multiple (liste d'identifiants). Interface : resources/views/filament/forms/media-field.blade.php
 * et public/js/admin/media-picker.js ; données : App\Http\Controllers\Admin\MediaBrowserController.
 */
class MediaField extends Field
{
    protected string $view = 'filament.forms.media-field';

    protected bool $multiple = false;

    protected bool $imagesOnly = true;

    public function multiple(bool $condition = true): static
    {
        $this->multiple = $condition;

        return $this;
    }

    public function imagesOnly(bool $condition = true): static
    {
        $this->imagesOnly = $condition;

        return $this;
    }

    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    public function isImagesOnly(): bool
    {
        return $this->imagesOnly;
    }

    /** Aperçu du média déjà choisi (affiché dans le champ avant d'ouvrir la fenêtre), ou null. */
    public function getCurrentPreview(): ?array
    {
        $id = $this->getState();

        if ($this->multiple || blank($id) || ! is_scalar($id)) {
            return null;
        }

        $asset = MediaAsset::with('media')->find($id);

        return $asset ? static::describe($asset) : null;
    }

    /** @return array<string, mixed> */
    public static function describe(MediaAsset $asset): array
    {
        $file = $asset->file();

        return [
            'id' => $asset->getKey(),
            'title' => $asset->label(),
            'alt' => (string) $asset->alt,
            'thumb' => $asset->previewUrl('thumb'),
            'card' => $asset->previewUrl('card'),
            'full' => $asset->isImage() ? $asset->url('') : null,
            'width' => $asset->dimensions()[0],
            'height' => $asset->dimensions()[1],
            'filename' => $file?->file_name,
            'size' => $file?->human_readable_size,
            'type' => $asset->isPdf() ? 'pdf' : 'image',
            'date' => $asset->created_at?->translatedFormat('j F Y'),
            'edit_url' => MediaAssetResource::getUrl('edit', ['record' => $asset]),
        ];
    }
}
