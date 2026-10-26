<?php

use App\Filament\Resources\MediaAssets\Pages\CreateMediaAsset;
use App\Filament\Resources\MediaAssets\Pages\ListMediaAssets;
use App\Models\MediaAsset;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('public');
    Storage::fake('local');
    config(['media-library.disk_name' => 'public']);
});

function editorUser(): User
{
    return tap(User::factory()->create())->assignRole(RolesAndPermissionsSeeder::EDITOR);
}

it('lists media for editors', function () {
    MediaAsset::factory()->withImage()->create(['title' => 'Campus vu du ciel']);

    actingAs(editorUser());

    Livewire::test(ListMediaAssets::class)
        ->assertOk()
        ->assertSee('Campus vu du ciel');
});

it('requires alternative text when creating a media', function () {
    actingAs(editorUser());

    Livewire::test(CreateMediaAsset::class)
        ->fillForm(['file' => UploadedFile::fake()->image('campus.jpg', 1200, 800), 'alt' => ''])
        ->call('create')
        ->assertHasFormErrors(['alt' => 'required']);

    expect(MediaAsset::count())->toBe(0);
});

it('creates a media with its file and conversions', function () {
    actingAs(editorUser());

    Livewire::test(CreateMediaAsset::class)
        ->fillForm([
            'file' => UploadedFile::fake()->image('campus.jpg', 1200, 800),
            'title' => 'Campus',
            'alt' => 'Vue du campus de l\'UAZ',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $asset = MediaAsset::first();

    expect($asset->alt)->toBe('Vue du campus de l\'UAZ')
        ->and($asset->file())->not->toBeNull()
        ->and($asset->file()->hasGeneratedConversion('card'))->toBeTrue();
});

it('uploads several files at once with one alt text each', function () {
    actingAs(editorUser());

    Livewire::test(ListMediaAssets::class)
        ->callAction('bulkUpload', [
            'files' => [
                UploadedFile::fake()->image('a.jpg', 800, 600),
                UploadedFile::fake()->image('b.jpg', 800, 600),
            ],
            'items' => [
                ['file' => 'a.jpg', 'alt' => 'Première image'],
                ['file' => 'b.jpg', 'alt' => 'Seconde image'],
            ],
        ])
        ->assertHasNoFormErrors();

    expect(MediaAsset::count())->toBe(2)
        ->and(MediaAsset::pluck('alt')->sort()->values()->all())->toBe(['Première image', 'Seconde image']);
});

it('refuses a bulk upload when an alt text is missing', function () {
    actingAs(editorUser());

    Livewire::test(ListMediaAssets::class)
        ->callAction('bulkUpload', [
            'files' => [UploadedFile::fake()->image('a.jpg', 800, 600)],
            'items' => [['file' => 'a.jpg', 'alt' => '']],
        ])
        ->assertHasFormErrors();

    expect(MediaAsset::count())->toBe(0);
});
