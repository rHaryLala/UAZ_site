<?php

use App\Models\Formation;
use App\Models\ImportRecord;
use App\Models\MediaAsset;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    config(['media-library.disk_name' => 'public']);

    ob_start();
    $image = imagecreatetruecolor(1200, 800);
    imagejpeg($image, null, 80);
    $jpeg = (string) ob_get_clean();

    Http::fake(['https://zurcher.edu.mg/*' => Http::response($jpeg, 200, ['Content-Type' => 'image/jpeg'])]);
});

it('imports the site photos and places them on content that has no image', function () {
    $law = Formation::factory()->create(['slug' => 'licence-en-droit', 'cover_id' => null]);

    $this->artisan('media:import-site-images --only=soutenir-projet')->assertSuccessful();

    $law->refresh();
    expect($law->cover)->not->toBeNull()
        ->and($law->cover->title)->toBe('Soutenance de mémoires')
        ->and($law->cover->alt)->not->toBe('')
        ->and($law->cover->file()->hasGeneratedConversion('card'))->toBeTrue()
        ->and(ImportRecord::lookup('site-image', '/images/site/soutenir-projet.jpg'))->not->toBeNull();
});

it('never replaces an image an editor already chose', function () {
    $mine = MediaAsset::factory()->withImage(800, 600)->create(['title' => 'Ma photo']);
    $law = Formation::factory()->create(['slug' => 'licence-en-droit', 'cover_id' => $mine->id]);

    $this->artisan('media:import-site-images --only=soutenir-projet')->assertSuccessful();

    expect($law->refresh()->cover_id)->toBe($mine->id);
});

it('downloads each photo only once, however many times it runs', function () {
    Formation::factory()->create(['slug' => 'licence-en-droit', 'cover_id' => null]);

    $this->artisan('media:import-site-images --only=soutenir-projet')->assertSuccessful();
    $first = MediaAsset::count();
    Http::assertSentCount($first);

    $this->artisan('media:import-site-images --only=soutenir-projet')->assertSuccessful();

    expect(MediaAsset::count())->toBe($first);
    Http::assertSentCount($first);
});

it('writes nothing in a dry run', function () {
    $law = Formation::factory()->create(['slug' => 'licence-en-droit', 'cover_id' => null]);

    $this->artisan('media:import-site-images --only=soutenir-projet --dry-run')->assertSuccessful();

    expect(MediaAsset::count())->toBe(0)->and($law->refresh()->cover_id)->toBeNull();
    Http::assertNothingSent();
});

it('fails, without touching anything, when the old site does not answer', function () {
    Http::fake(['https://down.example/*' => Http::response('', 500)]);
    $law = Formation::factory()->create(['slug' => 'licence-en-droit', 'cover_id' => null]);

    $this->artisan('media:import-site-images --only=soutenir-projet --from=https://down.example')->assertFailed();

    expect(MediaAsset::count())->toBe(0)->and($law->refresh()->cover_id)->toBeNull();
});
