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

    Livewire::test(ListMediaAssets::class)->loadTable()
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

it('gives every media an image preview, including PDFs', function () {
    $pdf = \App\Models\MediaAsset::create(['title' => 'Emploi du temps de Théologie', 'alt' => 'Planning']);
    $path = sys_get_temp_dir().'/edt-'.uniqid().'.pdf';
    file_put_contents($path, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF");
    $pdf->addMedia($path)->toMediaCollection(\App\Models\MediaAsset::COLLECTION);

    $preview = $pdf->refresh()->previewUrl('thumb');

    expect($pdf->isPdf())->toBeTrue()
        ->and($preview)->toStartWith('data:image/svg+xml;base64,')
        ->and(base64_decode(substr($preview, strlen('data:image/svg+xml;base64,'))))->toContain('PDF')->toContain('Emploi');
});

it('serves the media browser to authorised users only, with search and type filters', function () {
    \App\Models\MediaAsset::create(['title' => 'Bibliothèque', 'alt' => 'Salle de lecture']);
    \App\Models\MediaAsset::create(['title' => 'Campus', 'alt' => 'Vue du campus']);

    $this->getJson('/admin/media-browser')->assertForbidden();

    $this->actingAs(\App\Models\User::factory()->create()->assignRole('editor'));
    $this->getJson('/admin/media-browser?q=Biblio')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Bibliothèque')->assertJsonPath('has_more', false);
    $this->getJson('/admin/media-browser?type=pdf')->assertOk()->assertJsonCount(0, 'data');
});

it('refuses uploads without alternative text or with a wrong format, and creates the media otherwise', function () {
    $this->actingAs(\App\Models\User::factory()->create()->assignRole('editor'));
    $png = \Illuminate\Http\UploadedFile::fake()->image('photo.png', 400, 300);

    $this->post('/admin/media-browser/upload', ['files' => [$png], 'alts' => ['']], ['Accept' => 'application/json'])->assertStatus(422);
    $this->post('/admin/media-browser/upload', ['files' => [\Illuminate\Http\UploadedFile::fake()->create('virus.exe', 10)], 'alts' => ['Un fichier']], ['Accept' => 'application/json'])->assertStatus(422);

    $this->post('/admin/media-browser/upload', ['files' => [$png], 'alts' => ['Une photo de test']], ['Accept' => 'application/json'])
        ->assertCreated()->assertJsonPath('data.0.alt', 'Une photo de test');

    expect(\App\Models\MediaAsset::where('alt', 'Une photo de test')->exists())->toBeTrue();
});

it('forbids uploads to users without any role', function () {
    $this->actingAs(\App\Models\User::factory()->create());

    $this->post('/admin/media-browser/upload', ['files' => [\Illuminate\Http\UploadedFile::fake()->image('p.png')], 'alts' => ['Test']], ['Accept' => 'application/json'])->assertForbidden();
});

it('loads the grid progressively, like the WordPress media library', function () {
    $this->actingAs(\App\Models\User::factory()->create()->assignRole('editor'));
    session(['media_layout' => 'grid']);
    foreach (range(1, 40) as $i) {
        \App\Models\MediaAsset::create(['title' => "Image $i", 'alt' => "Image numéro $i"]);
    }

    $page = \Livewire\Livewire::test(\App\Filament\Resources\MediaAssets\Pages\ListMediaAssets::class)->loadTable();

    expect($page->instance()->mediaCounts())->toBe(['shown' => 36, 'total' => 40]);

    $page->call('loadMoreMedia');
    expect($page->instance()->mediaCounts())->toBe(['shown' => 40, 'total' => 40]);

    $page->set('tableSearch', 'numéro 7');
    expect($page->instance()->mediaCounts()['shown'])->toBe(4);
});
