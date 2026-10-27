<?php

use App\Filament\Forms\MediaField;
use App\Filament\Resources\NewsPosts\Pages\CreateNewsPost;
use App\Filament\Support\MediaPicker;
use App\Models\MediaAsset;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('public');
    Storage::fake('local');
    config(['media-library.disk_name' => 'public', 'uaz.media.conversions' => []]);
    actingAs(tap(User::factory()->create())->assignRole(RolesAndPermissionsSeeder::EDITOR));
});

it('describes a media for the picker and renders the field in the news form', function () {
    $asset = MediaAsset::create(['title' => 'Campus', 'alt' => 'Vue du campus']);

    $described = MediaField::describe($asset);

    expect($described)->toMatchArray(['id' => $asset->id, 'title' => 'Campus', 'alt' => 'Vue du campus'])
        ->and($described)->toHaveKeys(['thumb', 'card', 'filename', 'size', 'type', 'date', 'edit_url']);

    Livewire::test(CreateNewsPost::class)->assertSeeHtml('uaz-mp');
});

it('keeps the single and multiple modes of the field apart', function () {
    expect(MediaPicker::make('cover_id')->isMultiple())->toBeFalse()
        ->and(MediaPicker::make('photos')->multiple()->isMultiple())->toBeTrue();
});
