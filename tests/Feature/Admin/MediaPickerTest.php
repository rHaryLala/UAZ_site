<?php

use App\Filament\Resources\NewsPosts\Pages\CreateNewsPost;
use App\Models\MediaAsset;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
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

it('uploads a new image from the news form, with mandatory alt text, and selects it', function () {
    Livewire::test(CreateNewsPost::class)
        ->callAction(
            TestAction::make('createOption')->schemaComponent('cover_id'),
            ['file' => UploadedFile::fake()->image('une.jpg', 1600, 900), 'alt' => 'Étudiants sur le campus'],
        )
        ->assertHasNoActionErrors();

    $asset = MediaAsset::sole();

    expect($asset->alt)->toBe('Étudiants sur le campus')->and($asset->file())->not->toBeNull();
});

it('refuses to create the image without alt text', function () {
    Livewire::test(CreateNewsPost::class)
        ->callAction(
            TestAction::make('createOption')->schemaComponent('cover_id'),
            ['file' => UploadedFile::fake()->image('une.jpg', 1600, 900), 'alt' => ''],
        )
        ->assertHasActionErrors(['alt' => 'required']);

    expect(MediaAsset::count())->toBe(0);
});
