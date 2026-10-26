<?php

namespace App\Filament\Resources\PhotoAlbums\Concerns;

/**
 * Les photos d'un album sont une relation ordonnée (table pivot avec « position ») :
 * on les retire des données du modèle, puis on les synchronise après l'enregistrement.
 */
trait SyncsAlbumPhotos
{
    /** @var list<int> */
    protected array $photoIds = [];

    protected function stashPhotos(array $data): array
    {
        $this->photoIds = collect($data['photos'] ?? [])
            ->pluck('media_asset_id')->filter()->unique()->values()->all();

        unset($data['photos'], $data['quick_add']);

        return $data;
    }

    protected function syncPhotos(): void
    {
        $this->record->photos()->sync(
            collect($this->photoIds)->mapWithKeys(fn ($id, $position) => [$id => ['position' => $position]])->all(),
        );
    }
}
