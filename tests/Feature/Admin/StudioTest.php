<?php

use App\Models\MediaAsset;
use App\Models\StudioDesign;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;

// Studio UAZ : la page de l'éditeur et les visuels enregistrés de chaque personne (le dessin se fait dans le navigateur).

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->editor = User::factory()->create()->assignRole(RolesAndPermissionsSeeder::EDITOR);
    $this->other = User::factory()->create()->assignRole(RolesAndPermissionsSeeder::EDITOR);
    $this->plain = User::factory()->create();
});

function studioPayload(array $override = []): array
{
    return array_replace_recursive([
        'name' => 'Culte du Sabbat',
        'template' => 'accueil',
        'format' => '16:9',
        'data' => ['champs' => ['titre1' => 'Culte du', 'titre2' => 'Sabbat', 'filigrane' => true], 'photos' => []],
    ], $override);
}

it('opens the studio for people who can add media, and only them', function () {
    $this->get(route('admin.studio.index'))->assertRedirect('/admin/login');
    $this->actingAs($this->plain)->get(route('admin.studio.index'))->assertForbidden();

    $this->actingAs($this->editor)->get(route('admin.studio.index'))->assertOk()
        ->assertSee('Studio UAZ')->assertSee('js/admin/studio/editeur.js', false)->assertSee('"urls"', false);
});

it('saves, lists, reopens, updates and deletes a design, for its owner only', function () {
    $id = $this->actingAs($this->editor)->postJson(route('admin.studio.designs.store'), studioPayload())->assertCreated()->json('design.id');

    $list = $this->actingAs($this->editor)->getJson(route('admin.studio.designs.index'))->assertOk()->json('designs');
    expect($list)->toHaveCount(1)->and($list[0])->toMatchArray(['id' => $id, 'name' => 'Culte du Sabbat', 'template' => 'accueil']);

    $this->actingAs($this->editor)->getJson(route('admin.studio.designs.show', $id))->assertOk()
        ->assertJsonPath('design.data.champs.titre1', 'Culte du')->assertJsonPath('design.format', '16:9');

    $this->actingAs($this->editor)->putJson(route('admin.studio.designs.update', $id), studioPayload(['name' => 'Autre nom', 'format' => '1:1']))->assertOk();
    expect(StudioDesign::find($id))->name->toBe('Autre nom')->format->toBe('1:1');

    // Les visuels d'une autre personne restent invisibles et intouchables.
    $this->actingAs($this->other)->getJson(route('admin.studio.designs.index'))->assertOk()->assertJsonCount(0, 'designs');
    $this->actingAs($this->other)->getJson(route('admin.studio.designs.show', $id))->assertForbidden();
    $this->actingAs($this->other)->putJson(route('admin.studio.designs.update', $id), studioPayload())->assertForbidden();
    $this->actingAs($this->other)->deleteJson(route('admin.studio.designs.destroy', $id))->assertForbidden();
    $this->actingAs($this->plain)->getJson(route('admin.studio.designs.index'))->assertForbidden();

    $this->actingAs($this->editor)->deleteJson(route('admin.studio.designs.destroy', $id))->assertOk();
    expect(StudioDesign::count())->toBe(0);
});

it('cleans what the browser sends: no markup, bounded fields, valid slots, clamped framing', function () {
    $media = MediaAsset::create(['title' => 'Photo', 'alt' => 'Une photo']);

    $id = $this->actingAs($this->editor)->postJson(route('admin.studio.designs.store'), studioPayload([
        'name' => '<b>Gras</b> nom',
        'data' => [
            'champs' => ['titre1' => '<script>x()</script>'.str_repeat('a', 2000), 'mauvaise cle!' => 'x', 'ok_1' => 'valeur'],
            'photos' => [
                'photo1' => ['media' => $media->id, 'focus' => [9, -3], 'zoom' => 99],
                'pas valide!' => ['media' => $media->id],
                'photo2' => ['media' => 0],
                'photo3' => 'n’importe quoi',
            ],
        ],
    ]))->assertCreated()->json('design.id');

    $design = StudioDesign::find($id);
    expect($design->name)->toBe('Gras nom')
        ->and(mb_strlen($design->data['champs']['titre1']))->toBeLessThanOrEqual(800)->and($design->data['champs']['titre1'])->not->toContain('<')
        ->and($design->data['champs'])->toHaveKey('ok_1')->not->toHaveKey('mauvaise cle!')
        ->and($design->data['photos'])->toHaveCount(1)
        ->and($design->data['photos']['photo1'])->toMatchArray(['media' => $media->id, 'focus' => [1.0, 0.0], 'zoom' => 3.0]);
});

it('refuses an unknown format, a bad template name and an oversized or foreign thumbnail', function () {
    $post = fn (array $o) => $this->actingAs($this->editor)->postJson(route('admin.studio.designs.store'), studioPayload($o));

    $post(['format' => '3:2'])->assertUnprocessable();
    $post(['template' => '../etc'])->assertUnprocessable();
    $post(['thumb' => 'data:text/html;base64,PHNjcmlwdD4='])->assertUnprocessable();
    $post(['thumb' => 'data:image/jpeg;base64,'.str_repeat('A', 95000)])->assertUnprocessable();
    $post(['thumb' => 'data:image/jpeg;base64,/9j/4AAQ'])->assertCreated();
});

it('gives back the current address of each photo and drops a photo deleted since', function () {
    $keep = MediaAsset::create(['title' => 'Gardée', 'alt' => 'Photo gardée']);
    $keep->addMedia(UploadedFile::fake()->image('campus.jpg', 1200, 800))->toMediaCollection(MediaAsset::COLLECTION);
    $gone = MediaAsset::create(['title' => 'Perdue', 'alt' => 'Photo perdue']);

    $id = $this->actingAs($this->editor)->postJson(route('admin.studio.designs.store'), studioPayload(['data' => ['photos' => [
        'photo1' => ['media' => $keep->id, 'focus' => [0.4, 0.3], 'zoom' => 1.5],
        'photo2' => ['media' => $gone->id],
    ]]]))->assertCreated()->json('design.id');

    $gone->delete();

    $photos = $this->actingAs($this->editor)->getJson(route('admin.studio.designs.show', $id))->assertOk()->json('design.data.photos');
    expect($photos)->toHaveCount(1)->and($photos['photo1'])->toMatchArray(['media' => $keep->id, 'zoom' => 1.5, 'width' => 1200, 'height' => 800])
        ->and($photos['photo1']['url'])->toContain('/storage/');
});

it('lets the media browser give the studio the original address and size of a photo', function () {
    $asset = MediaAsset::create(['title' => 'Campus', 'alt' => 'Le campus']);
    $asset->addMedia(UploadedFile::fake()->image('campus.jpg', 1200, 800))->toMediaCollection(MediaAsset::COLLECTION);

    $first = $this->actingAs($this->editor)->getJson(route('admin.media-browser.browse', ['type' => 'image']))->assertOk()->json('data.0');
    expect($first)->toHaveKeys(['full', 'width', 'height'])->and($first['width'])->toBe(1200)->and($first['full'])->toContain('/storage/');
});

it('ships the engine and every template, with the French typography rules and the safe zones', function () {
    $engine = file_get_contents(public_path('js/admin/studio/moteur.js'));
    $templates = file_get_contents(public_path('js/admin/studio/modeles.js'));

    expect($engine)->toContain("'16:9': { w: 1920, h: 1080")->toContain("'9:16': { w: 1080, h: 1920")->toContain('function redimensionner(')->toContain('const typo =')
        ->and($templates)->toContain("ORDRE: ['accueil', 'direct', 'annonce', 'verset', 'felicitations']");
    foreach (['accueil', 'direct', 'annonce', 'verset', 'felicitations'] as $id) {
        expect($templates)->toContain("MODELES.{$id} = {");
    }
    foreach (['fonts/studio/inter-latin.woff2', 'images/studio/theme-clair.png', 'images/uaz-seal-384.png'] as $asset) {
        expect(public_path($asset))->toBeFile();
    }
});
