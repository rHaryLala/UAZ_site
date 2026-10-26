<?php

namespace App\Filament\Resources\PhotoAlbums\Pages;

use App\Filament\Resources\PhotoAlbums\Concerns\SyncsAlbumPhotos;
use App\Filament\Resources\PhotoAlbums\PhotoAlbumResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePhotoAlbum extends CreateRecord
{
    use SyncsAlbumPhotos;

    protected static string $resource = PhotoAlbumResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->stashPhotos($data);
    }

    protected function afterCreate(): void
    {
        $this->syncPhotos();
    }
}
