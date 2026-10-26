<?php

namespace App\Filament\Resources\PhotoAlbums\Pages;

use App\Filament\Resources\PhotoAlbums\Concerns\SyncsAlbumPhotos;
use App\Filament\Resources\PhotoAlbums\PhotoAlbumResource;
use App\Filament\Support\PublicationTable;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPhotoAlbum extends EditRecord
{
    use SyncsAlbumPhotos;

    protected static string $resource = PhotoAlbumResource::class;

    protected function getHeaderActions(): array
    {
        return [PublicationTable::previewAction(), DeleteAction::make()];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['photos'] = $this->record->photos
            ->map(fn ($photo) => ['media_asset_id' => $photo->getKey()])
            ->all();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->stashPhotos($data);
    }

    protected function afterSave(): void
    {
        $this->syncPhotos();
    }
}
