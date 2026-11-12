<?php

use App\Models\MediaAsset;
use Database\Factories\PlaceholderImage;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileUnacceptableForCollection;

beforeEach(function () {
    Storage::fake('public');
    config(['media-library.disk_name' => 'public']);
});

it('stores an image with its dimensions and generates WebP conversions', function () {
    $asset = MediaAsset::factory()->withImage(1600, 900)->create();
    $media = $asset->file();

    expect($media)->not->toBeNull()
        ->and($media->getCustomProperty('width'))->toBe(1600)
        ->and($media->getCustomProperty('height'))->toBe(900)
        ->and($media->hasGeneratedConversion('thumb'))->toBeTrue()
        ->and($media->hasGeneratedConversion('card'))->toBeTrue()
        ->and($media->hasGeneratedConversion('hero'))->toBeTrue();
});

it('shrinks oversized originals to the configured maximum width', function () {
    config(['uaz.media.original_max_width' => 1000]);

    $asset = MediaAsset::factory()->create();
    $asset->addMedia(PlaceholderImage::make(2400, 1200))->toMediaCollection(MediaAsset::COLLECTION);

    [$width, $height] = $asset->fresh()->dimensions();

    expect($width)->toBe(1000)->and($height)->toBe(500);
});

it('exposes picture data with responsive WebP and AVIF sources', function () {
    config(['uaz.media.avif' => true]);

    $asset = MediaAsset::factory()->withImage(1600, 900)->create();
    $data = $asset->pictureData('card');

    expect($data['webp'])->toContain('.webp')
        ->and($data['width'])->toBe(960)
        ->and($data['height'])->toBe(540)
        ->and($data['src'])->toContain('.webp');

    // AVIF seulement si GD le permet (c'est le cas sur le PHP de Laragon).
    if (function_exists('imageavif')) {
        expect($data['avif'])->toContain('.avif');
    }
});

it('rejects file types that are not images or PDFs', function () {
    $asset = MediaAsset::factory()->create();
    $file = tempnam(sys_get_temp_dir(), 'x').'.exe';
    file_put_contents($file, 'MZ');

    $asset->addMedia($file)->toMediaCollection(MediaAsset::COLLECTION);
})->throws(FileUnacceptableForCollection::class);

it('does not ask for AVIF files on a server that cannot produce them', function () {
    $asset = MediaAsset::factory()->withImage(1600, 900)->create();

    // Les photos sont arrivées avec leurs versions AVIF, mais ce serveur n'en génère pas : les conversions « *-avif » ne sont plus déclarées.
    config(['uaz.media.avif' => false]);
    $asset = MediaAsset::find($asset->id);
    $data = $asset->pictureData('card');

    expect($data['avif'])->toBeNull()->and($data['webp'])->not->toBeNull()->and($data['src'])->not->toBe('');
});
