<?php

use App\Enums\PublicationStatus;
use App\Filament\Support\PageBlocks;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\User;
use App\Support\EditMode;
use App\Support\VisualBlocks;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\StructureSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->page = Page::create([
        'title' => 'Page de test', 'slug' => 'page-de-test', 'summary' => 'Résumé', 'status' => PublicationStatus::Published, 'published_at' => now()->subDay(),
        'blocks' => [
            ['type' => 'rich_text', 'data' => ['heading' => 'Titre', 'content' => '<p>Texte</p>', 'width' => 'normal']],
            ['type' => 'quote', 'data' => ['text' => 'Citation', 'author' => 'A', 'role' => '']],
        ],
    ]);
    $this->editor = User::factory()->create()->assignRole(RolesAndPermissionsSeeder::EDITOR);
});

/** État valide minimal envoyé par l'éditeur. */
function state(array $blocks = null, string $title = 'Nouveau titre'): array
{
    return ['title' => $title, 'summary' => 'Nouveau résumé', 'blocks' => $blocks ?? [['type' => 'quote', 'data' => ['text' => 'Hello', 'author' => '', 'role' => '']]]];
}

it('describes the same block types as the classic form', function () {
    expect(array_keys(VisualBlocks::types()))->toBe(PageBlocks::TYPES);
});

it('sends visitors to the login and refuses people who cannot edit the page', function () {
    $this->get(route('admin.visual-editor.show', $this->page))->assertRedirect('/admin/login');
    $this->get(route('admin.visual-editor.preview', $this->page))->assertForbidden();
    $this->get(route('admin.visual-editor.canvas', $this->page))->assertForbidden();

    $this->actingAs(User::factory()->create())->get(route('admin.visual-editor.show', $this->page))->assertForbidden();
    $this->putJson(route('admin.visual-editor.draft', $this->page), state())->assertForbidden();
});

it('opens the editor for an editor, with the page state and the block library', function () {
    $this->actingAs($this->editor)->get(route('admin.visual-editor.show', $this->page))
        ->assertOk()
        ->assertSee('data-ve-frame', false)
        ->assertSee('Éditeur visuel')
        ->assertSee('"rich_text"', false)
        ->assertSee('Citation');

    $this->actingAs($this->editor)->get(route('admin.visual-editor.canvas', $this->page))->assertOk()->assertSee('ve-root', false);
});

it('explains why pages with a hand-built layout cannot be edited visually', function () {
    $this->seed(StructureSeeder::class);
    $about = Page::where('key', 'about')->firstOrFail();

    $this->actingAs($this->editor)->get(route('admin.visual-editor.show', $about))
        ->assertOk()
        ->assertSee('mise en page dessinée sur mesure')
        ->assertSee('/admin/content-pages/pages/'.$about->id.'/edit', false)
        ->assertDontSee('data-ve-frame', false);

    // Une page libre du site reste éditable.
    $this->actingAs($this->editor)->get(route('admin.visual-editor.show', Page::where('key', 'privacy')->firstOrFail()))->assertOk()->assertSee('data-ve-frame', false);
});

it('draws the page for the editor with block wrappers, and never leaks them to the public site', function () {
    $html = $this->actingAs($this->editor)->postJson(route('admin.visual-editor.render', $this->page), state([
        ['type' => 'rich_text', 'data' => ['heading' => 'Un titre', 'content' => '<p>Un texte</p>', 'width' => 'normal']],
        ['type' => 'quote', 'data' => ['text' => 'Une citation', 'author' => '', 'role' => '']],
    ]))->assertOk()->json('html');

    expect($html)->toContain('data-ve-hero')->toContain('data-ve-block="0"')->toContain('data-ve-type="quote"')->toContain('Nouveau titre')->toContain('Une citation');
    expect(EditMode::on())->toBeFalse();

    $this->get('/page-de-test')->assertOk()->assertDontSee('data-ve-block', false)->assertDontSee('data-ve-hero', false);
});

it('saves a draft without touching the published page, then discards it', function () {
    $this->actingAs($this->editor)->putJson(route('admin.visual-editor.draft', $this->page), state(title: 'Titre en brouillon'))
        ->assertOk()->assertJsonStructure(['saved_at']);

    $fresh = $this->page->fresh();
    expect($fresh->draft['title'])->toBe('Titre en brouillon')
        ->and($fresh->draft['blocks'][0]['data']['text'])->toBe('Hello')
        ->and($fresh->title)->toBe('Page de test')
        ->and($fresh->blocks)->toHaveCount(2);

    $this->get('/page-de-test')->assertOk()->assertSee('Page de test')->assertDontSee('Titre en brouillon');

    // L'éditeur rouvre le brouillon.
    $this->actingAs($this->editor)->get(route('admin.visual-editor.show', $this->page))->assertSee('Titre en brouillon');

    $this->actingAs($this->editor)->deleteJson(route('admin.visual-editor.draft.discard', $this->page))->assertOk();
    expect($this->page->fresh()->draft)->toBeNull();
});

it('previews the draft with the real layout of the site', function () {
    $this->actingAs($this->editor)->putJson(route('admin.visual-editor.draft', $this->page), state(title: 'Titre aperçu'));

    $this->actingAs($this->editor)->get(route('admin.visual-editor.preview', $this->page))->assertOk()->assertSee('Titre aperçu')->assertSee('Hello');
});

it('publishes the draft to the page and clears it', function () {
    $this->actingAs($this->editor)->postJson(route('admin.visual-editor.publish', $this->page), state(title: 'Titre publié'))
        ->assertOk()->assertJson(['published' => true]);

    $fresh = $this->page->fresh();
    expect($fresh->title)->toBe('Titre publié')->and($fresh->summary)->toBe('Nouveau résumé')->and($fresh->blocks)->toHaveCount(1)->and($fresh->draft)->toBeNull();

    $this->get('/page-de-test')->assertOk()->assertSee('Titre publié')->assertSee('Hello');
});

it('keeps publication for people who may publish: a contributor can only keep a draft of a published page', function () {
    $contributor = User::factory()->create()->assignRole(RolesAndPermissionsSeeder::CONTRIBUTOR);
    // Un contributeur modifie seulement ses propres brouillons : sa page est un brouillon, il peut alors l'appliquer.
    $own = Page::create(['title' => 'Mon brouillon', 'slug' => 'mon-brouillon', 'status' => PublicationStatus::Draft, 'user_id' => $contributor->id, 'blocks' => []]);

    $this->actingAs($contributor)->postJson(route('admin.visual-editor.publish', $own), state())->assertOk();

    // Il ne peut ni modifier ni publier une page publiée.
    $this->actingAs($contributor)->putJson(route('admin.visual-editor.draft', $this->page), state())->assertForbidden();
    $this->actingAs($contributor)->postJson(route('admin.visual-editor.publish', $this->page), state())->assertForbidden();
});

it('rejects invalid content on the server whatever the browser sends', function () {
    $post = fn (array $blocks) => $this->actingAs($this->editor)->putJson(route('admin.visual-editor.draft', $this->page), state($blocks));

    $post([['type' => 'script', 'data' => []]])->assertUnprocessable()->assertJsonValidationErrors('blocks.0.type');
    $post([['type' => 'cta', 'data' => ['title' => 'T', 'text' => '', 'button_label' => 'Go', 'button_url' => 'javascript:alert(1)']]])->assertUnprocessable()->assertJsonValidationErrors('blocks.0.data.button_url');
    $post([['type' => 'quote', 'data' => ['text' => str_repeat('a', 601), 'author' => '', 'role' => '']]])->assertUnprocessable()->assertJsonValidationErrors('blocks.0.data.text');
    $post([['type' => 'image', 'data' => ['image_id' => 999999, 'caption' => '', 'size' => 'wide']]])->assertUnprocessable()->assertJsonValidationErrors('blocks');
    $this->actingAs($this->editor)->putJson(route('admin.visual-editor.draft', $this->page), ['title' => '', 'blocks' => []])->assertUnprocessable()->assertJsonValidationErrors('title');
    $this->actingAs($this->editor)->putJson(route('admin.visual-editor.draft', $this->page), ['title' => 'Ok', 'blocks' => array_fill(0, 81, ['type' => 'quote', 'data' => []])])->assertUnprocessable();

    expect($this->page->fresh()->draft)->toBeNull();
});

it('sanitises rich text and drops unknown fields', function () {
    $clean = VisualBlocks::normalize([[
        'type' => 'rich_text',
        'data' => ['heading' => ' Titre ', 'content' => '<p onclick="x()">Bonjour <script>alert(1)</script><strong>monde</strong></p><iframe src="https://x.test"></iframe>', 'width' => 'enorme', 'extra' => 'intrus'],
    ]]);

    expect($clean[0]['data'])->toHaveKeys(['heading', 'content', 'width'])->not->toHaveKey('extra')
        ->and($clean[0]['data']['heading'])->toBe('Titre')
        ->and($clean[0]['data']['width'])->toBe('normal')
        ->and($clean[0]['data']['content'])->toContain('<strong>monde</strong>')->not->toContain('script')->not->toContain('onclick')->not->toContain('iframe');
});

it('is lenient for a draft but strict for publication on required fields', function () {
    $incomplete = [['type' => 'cta', 'data' => ['title' => '', 'text' => '', 'button_label' => '', 'button_url' => '']]];

    expect(VisualBlocks::normalize($incomplete)[0]['data']['title'])->toBe('');
    expect(fn () => VisualBlocks::normalize($incomplete, strict: true))->toThrow(Illuminate\Validation\ValidationException::class);

    $this->actingAs($this->editor)->putJson(route('admin.visual-editor.draft', $this->page), state($incomplete))->assertOk();
    $this->actingAs($this->editor)->postJson(route('admin.visual-editor.publish', $this->page), state($incomplete))->assertUnprocessable();
    expect($this->page->fresh()->blocks)->toHaveCount(2);
});

it('keeps images of the media library and lists them for the panel', function () {
    $asset = MediaAsset::create(['title' => 'Photo', 'alt' => 'Une photo']);
    $blocks = [['type' => 'gallery', 'data' => ['title' => 'G', 'images' => [['image_id' => $asset->id, 'caption' => 'Légende']]]]];

    expect(VisualBlocks::normalize($blocks)[0]['data']['images'][0]['image_id'])->toBe($asset->id)
        ->and(VisualBlocks::mediaIds($blocks))->toBe([$asset->id]);

    $json = $this->actingAs($this->editor)->postJson(route('admin.visual-editor.render', $this->page), state($blocks))->assertOk()->json();
    expect($json['media'])->toHaveKey((string) $asset->id);
});

it('creates fresh blocks that are valid as they are', function () {
    foreach (array_keys(VisualBlocks::types()) as $type) {
        $block = VisualBlocks::fresh($type);

        expect(fn () => VisualBlocks::normalize([$block]))->not->toThrow(Illuminate\Validation\ValidationException::class, $type);
    }

    expect(VisualBlocks::fresh('inconnu'))->toBeNull();
});

it('offers the visual editor from the classic form of a page', function () {
    Livewire\Livewire::actingAs($this->editor)
        ->test(App\Filament\Resources\ContentPages\Pages\EditPage::class, ['record' => $this->page->getRouteKey()])
        ->assertActionExists('visual');
});
