<?php

use App\Filament\Resources\Newsletters\Pages\CreateNewsletter;
use App\Filament\Resources\Newsletters\Pages\ListNewsletters;
use App\Jobs\SendNewsletter;
use App\Jobs\SendNewsletterMessage;
use App\Mail\NewsletterMessage;
use App\Models\MediaAsset;
use App\Models\Newsletter;
use App\Models\NewsletterDelivery;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use App\Support\NewsletterBlocks;
use App\Support\NewsletterRenderer;
use App\Support\NewsletterTemplates;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Symfony\Component\Mime\Email;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->editor = User::factory()->create()->assignRole(RolesAndPermissionsSeeder::EDITOR);
    $this->admin = User::factory()->create()->assignRole(RolesAndPermissionsSeeder::SUPER_ADMIN);
});

function draftLetter(array $attributes = []): Newsletter
{
    return Newsletter::create($attributes + [
        'subject' => 'Lettre de test', 'blocks' => NewsletterBlocks::starter(), 'theme' => NewsletterBlocks::defaultTheme(), 'status' => Newsletter::DRAFT,
    ]);
}

function subscriber(string $email, string $state = 'active'): NewsletterSubscriber
{
    return NewsletterSubscriber::create(match ($state) {
        'pending' => ['email' => $email, 'confirmation_sent_at' => now()],
        'gone' => ['email' => $email, 'confirmed_at' => now()->subDay(), 'unsubscribed_at' => now()],
        default => ['email' => $email, 'confirmed_at' => now()],
    });
}

/** État valide envoyé par l'éditeur. */
function letterState(?array $blocks = null, string $subject = 'Objet'): array
{
    return ['subject' => $subject, 'preheader' => 'Résumé', 'blocks' => $blocks ?? NewsletterBlocks::starter(), 'theme' => NewsletterBlocks::defaultTheme()];
}

// --- Nettoyage des blocs -----------------------------------------------------------------------------------------------------------------

it('drops unknown block types and caps the number of blocks', function () {
    $blocks = NewsletterBlocks::normalize([
        ['type' => 'heading', 'data' => ['text' => 'Bonjour']],
        ['type' => 'script', 'data' => ['text' => 'x']],
        'pas un bloc',
    ]);

    expect($blocks)->toHaveCount(1)->and($blocks[0]['type'])->toBe('heading');
    expect(NewsletterBlocks::normalize(array_fill(0, 100, ['type' => 'divider'])))->toHaveCount(NewsletterBlocks::MAX_BLOCKS);
    expect(NewsletterBlocks::normalize('n’importe quoi'))->toBe([]);
});

it('strips markup from plain fields and cleans rich text', function () {
    $blocks = NewsletterBlocks::normalize([
        ['type' => 'heading', 'data' => ['text' => '<script>alert(1)</script>Titre <b>net</b>']],
        ['type' => 'text', 'data' => ['html' => '<p onclick="x()">Texte <strong>fort</strong></p><script>alert(2)</script>']],
    ]);

    expect($blocks[0]['data']['text'])->not->toContain('<')
        ->and($blocks[1]['data']['html'])->toContain('<strong>fort</strong>')
        ->not->toContain('<script')->not->toContain('onclick');
});

it('keeps only #rrggbb colours and http, mailto and tel links', function () {
    $blocks = NewsletterBlocks::normalize([
        ['type' => 'button', 'data' => ['label' => 'Lire', 'url' => 'javascript:alert(1)', 'color' => 'red']],
        ['type' => 'button', 'data' => ['label' => 'Lire', 'url' => 'https://exemple.org/page', 'color' => '#ABCDEF']],
        ['type' => 'button', 'data' => ['label' => 'Écrire', 'url' => 'mailto:contact@exemple.org']],
    ]);

    expect($blocks[0]['data']['url'])->toBe('')
        ->and($blocks[0]['data']['color'])->not->toBe('red')
        ->and($blocks[1]['data']['url'])->toBe('https://exemple.org/page')
        ->and($blocks[1]['data']['color'])->toBe('#abcdef')
        ->and($blocks[2]['data']['url'])->toBe('mailto:contact@exemple.org');
});

it('falls back to the default theme for invalid values', function () {
    $theme = NewsletterBlocks::theme(['background' => 'url(x)', 'accent' => '#112233', 'font' => 'comic']);

    expect($theme['background'])->toBe(NewsletterBlocks::defaultTheme()['background'])
        ->and($theme['accent'])->toBe('#112233')
        ->and($theme['font'])->toBeIn(['serif', 'sans']);
});

// --- Dessin du courrier -----------------------------------------------------------------------------------------------------------------

it('draws the letter with a personal unsubscribe link and no editing marks when sent', function () {
    $html = NewsletterRenderer::html(letterState(), null, 'https://exemple.org/desinscription?signature=abc');

    expect($html)->toContain('https://exemple.org/desinscription?signature=abc')->not->toContain('data-nl-block')->not->toContain('data-nl-field');

    $editing = NewsletterRenderer::html(letterState(), null, null, true, 0);
    expect($editing)->toContain('data-nl-block')->toContain('data-nl-field');
});

it('produces a plain-text version with the unsubscribe link', function () {
    $text = NewsletterRenderer::text(letterState(), 'https://exemple.org/stop');

    expect($text)->toContain('https://exemple.org/stop')->not->toContain('<');
});

// --- Envoi ------------------------------------------------------------------------------------------------------------------------------

it('sends the letter to active subscribers only, once each', function () {
    Mail::fake();
    subscriber('un@exemple.org');
    subscriber('deux@exemple.org');
    subscriber('attente@exemple.org', 'pending');
    subscriber('parti@exemple.org', 'gone');
    $letter = draftLetter();

    $letter->send();

    $letter->refresh();
    expect($letter->recipients_count)->toBe(2)->and($letter->status)->toBe(Newsletter::SENT)->and($letter->sent_at)->not->toBeNull();
    Mail::assertSent(NewsletterMessage::class, 2);
    Mail::assertSent(NewsletterMessage::class, fn ($mail) => $mail->hasTo('un@exemple.org') && $mail->hasTo('un@exemple.org'));
    Mail::assertNotSent(NewsletterMessage::class, fn ($mail) => $mail->hasTo('attente@exemple.org') || $mail->hasTo('parti@exemple.org'));
    expect($letter->deliveries()->where('status', 'sent')->count())->toBe(2);

    // Relancer le travail n'envoie rien de plus.
    SendNewsletterMessage::dispatchSync($letter->id, NewsletterSubscriber::where('email', 'un@exemple.org')->value('id'));
    Mail::assertSent(NewsletterMessage::class, 2);
});

it('refuses to send a letter twice, or without any active subscriber', function () {
    Mail::fake();
    $empty = draftLetter();
    expect(fn () => $empty->send())->toThrow(LogicException::class, 'Aucun abonné actif');

    subscriber('un@exemple.org');
    $letter = draftLetter();
    $letter->send();

    expect(fn () => $letter->send())->toThrow(LogicException::class, 'déjà été envoyée');
});

it('skips a subscriber who unsubscribed before the message left', function () {
    Mail::fake();
    $leaving = subscriber('un@exemple.org');
    subscriber('deux@exemple.org');
    $letter = draftLetter();
    Bus::fake([SendNewsletter::class]); // l'envoi d'ensemble est suspendu : on joue les envois individuels à la main
    $letter->send();
    $leaving->update(['unsubscribed_at' => now()]);

    // Le travail d'ensemble crée un travail par abonné actif ; on les joue ici à la main, l'un d'eux s'étant désinscrit entre-temps.
    foreach (NewsletterSubscriber::orderBy('id')->get() as $sub) {
        SendNewsletterMessage::dispatchSync($letter->id, $sub->id);
    }

    Mail::assertSent(NewsletterMessage::class, 1);
    expect(NewsletterDelivery::where('newsletter_subscriber_id', $leaving->id)->value('status'))->toBe('skipped')
        ->and($letter->fresh()->status)->toBe(Newsletter::SENT);
});

it('records a failed delivery and carries on with the next subscriber', function () {
    subscriber('un@exemple.org');
    subscriber('deux@exemple.org');
    $letter = draftLetter();

    Mail::shouldReceive('to')->twice()->andReturnSelf();
    Mail::shouldReceive('send')->once()->andThrow(new RuntimeException('Serveur de courrier injoignable'));
    Mail::shouldReceive('send')->once()->andReturnNull();

    $letter->send();

    $letter->refresh();
    expect($letter->failedCount())->toBe(1)->and($letter->sentCount())->toBe(1)->and($letter->status)->toBe(Newsletter::SENT)
        ->and(NewsletterDelivery::where('status', 'failed')->value('error'))->toContain('injoignable');
});

it('queues one job per active subscriber', function () {
    Bus::fake();
    subscriber('un@exemple.org');
    subscriber('deux@exemple.org');
    subscriber('parti@exemple.org', 'gone');
    $letter = draftLetter();
    $letter->send();

    Bus::assertDispatched(SendNewsletter::class);
    expect($letter->fresh()->status)->toBe(Newsletter::SENDING);

    (new SendNewsletter($letter->id))->handle();
    Bus::assertDispatched(SendNewsletterMessage::class, 2);
});

it('puts a personal signed unsubscribe link and header in each message', function () {
    $sub = subscriber('un@exemple.org');
    $mail = new NewsletterMessage(draftLetter(), $sub);

    $mail->assertSeeInHtml('/newsletter/desinscription/'.$sub->id);
    expect($mail->headers()->text['List-Unsubscribe'])->toContain('/newsletter/desinscription/'.$sub->id)->toContain('signature=');
    expect((new NewsletterMessage(draftLetter(), null, test: true))->envelope()->subject)->toStartWith('[TEST] ');
});

// --- Lettre figée -----------------------------------------------------------------------------------------------------------------------

it('freezes a letter once it has left, even for the super-administrator', function () {
    $letter = draftLetter(['status' => Newsletter::SENT, 'sent_at' => now(), 'recipients_count' => 1]);

    expect(fn () => $letter->update(['subject' => 'Autre']))->toThrow(LogicException::class);
    expect(fn () => $letter->delete())->toThrow(LogicException::class);
    expect($this->admin->can('update', $letter))->toBeTrue(); // le super-administrateur passe les politiques : c'est le modèle qui protège

    $draft = draftLetter();
    $draft->update(['subject' => 'Modifiée']);
    expect($draft->fresh()->subject)->toBe('Modifiée');
});

// --- Droits et points d'accès -----------------------------------------------------------------------------------------------------------

it('sends visitors to the login and refuses people without the publishing right', function () {
    $letter = draftLetter();

    $this->get(route('admin.newsletter-editor.show', $letter))->assertRedirect('/admin/login');
    $this->postJson(route('admin.newsletter-editor.render', $letter), letterState())->assertUnauthorized();
    $this->putJson(route('admin.newsletter-editor.save', $letter), letterState())->assertUnauthorized();

    $contributor = User::factory()->create();
    $this->actingAs($contributor)->get(route('admin.newsletter-editor.show', $letter))->assertForbidden();
    $this->actingAs($contributor)->putJson(route('admin.newsletter-editor.save', $letter), letterState())->assertForbidden();
    $this->actingAs($contributor)->postJson(route('admin.newsletter-editor.send', $letter), letterState())->assertForbidden();
});

it('opens the editor and renders the artboard for an editor', function () {
    $letter = draftLetter();

    $this->actingAs($this->editor)->get(route('admin.newsletter-editor.show', $letter))
        ->assertOk()->assertSee('data-nl-frame', false)->assertSee('"heading"', false);

    $this->actingAs($this->editor)->postJson(route('admin.newsletter-editor.render', $letter), letterState())
        ->assertOk()->assertJsonPath('blocks.0.type', 'heading')->assertJsonStructure(['html', 'blocks', 'media']);
});

it('saves a sanitised draft', function () {
    $letter = draftLetter();
    $state = letterState([['type' => 'heading', 'data' => ['text' => '<script>x</script>Titre']], ['type' => 'nope']], '<b>Nouvel</b> objet');

    $this->actingAs($this->editor)->putJson(route('admin.newsletter-editor.save', $letter), $state)->assertOk()->assertJsonStructure(['saved_at']);

    $letter->refresh();
    expect($letter->subject)->toBe('Nouvel objet')->and($letter->blocks)->toHaveCount(1)->and($letter->blocks[0]['data']['text'])->not->toContain('<');
});

it('refuses to save or send a letter that has already been sent', function () {
    $letter = draftLetter(['status' => Newsletter::SENT, 'sent_at' => now(), 'recipients_count' => 1]);

    foreach ([$this->editor, $this->admin] as $user) {
        $this->actingAs($user)->putJson(route('admin.newsletter-editor.save', $letter), letterState(null, 'Piraté'))->assertForbidden();
        $this->actingAs($user)->postJson(route('admin.newsletter-editor.send', $letter), letterState())->assertForbidden();
    }

    expect($letter->fresh()->subject)->toBe('Lettre de test');
});

it('renders a sent letter without editing marks', function () {
    $letter = draftLetter(['status' => Newsletter::SENT, 'sent_at' => now(), 'recipients_count' => 1]);

    $html = $this->actingAs($this->admin)->postJson(route('admin.newsletter-editor.render', $letter), letterState())->assertOk()->json('html');

    expect($html)->not->toContain('data-nl-block');
});

it('sends a test message to one address without touching subscribers or the letter', function () {
    Mail::fake();
    subscriber('un@exemple.org');
    $letter = draftLetter();

    $this->actingAs($this->editor)->postJson(route('admin.newsletter-editor.test', $letter), letterState(null, 'Essai') + ['to' => 'moi@exemple.org'])->assertOk();
    $this->actingAs($this->editor)->postJson(route('admin.newsletter-editor.test', $letter), letterState() + ['to' => 'pas-une-adresse'])->assertUnprocessable();

    Mail::assertSent(NewsletterMessage::class, fn ($mail) => $mail->hasTo('moi@exemple.org'));
    Mail::assertSent(NewsletterMessage::class, 1);
    expect($letter->fresh()->status)->toBe(Newsletter::DRAFT)->and($letter->deliveries()->count())->toBe(0);
});

it('launches the sending from the editor, and demands a subject and an audience', function () {
    Mail::fake();
    $letter = draftLetter();

    $this->actingAs($this->editor)->postJson(route('admin.newsletter-editor.send', $letter), letterState(null, ''))->assertUnprocessable();
    $this->actingAs($this->editor)->postJson(route('admin.newsletter-editor.send', $letter), letterState())->assertUnprocessable()
        ->assertJsonPath('message', fn ($m) => str_contains($m, 'Aucun abonné'));

    subscriber('un@exemple.org');
    $this->actingAs($this->editor)->postJson(route('admin.newsletter-editor.send', $letter), letterState(null, 'Départ'))
        ->assertOk()->assertJsonPath('recipients', 1);

    expect($letter->fresh())->subject->toBe('Départ')->status->toBe(Newsletter::SENT);
    Mail::assertSent(NewsletterMessage::class, 1);
});

// --- Écrans d'administration ------------------------------------------------------------------------------------------------------------

it('lists the letters and creates one with a starter content, then goes to the editor', function () {
    $this->actingAs($this->editor);
    Livewire::test(ListNewsletters::class)->assertOk();

    $component = Livewire::test(CreateNewsletter::class)->fillForm(['subject' => 'Ma première lettre'])->call('create')->assertHasNoFormErrors();

    $letter = Newsletter::sole();
    expect($letter->status)->toBe(Newsletter::DRAFT)->and($letter->blocks)->not->toBeEmpty()->and($letter->created_by)->toBe($this->editor->id);
    $component->assertRedirect(route('admin.newsletter-editor.show', $letter));
});

it('hides the newsletter screens from people without the publishing right', function () {
    $this->actingAs(User::factory()->create())->get('/admin/newsletters')->assertForbidden();
});

// --- Blocs imbriqués : colonnes et bandeaux -----------------------------------------------------------------------------------------------

it('keeps the tree of a columns block and sanitises what is inside', function () {
    $blocks = NewsletterBlocks::normalize([[
        'id' => 'abcd1234ef', 'type' => 'columns', 'data' => ['count' => 2, 'ratio' => '1:2'],
        'children' => [
            [['type' => 'heading', 'data' => ['text' => '<script>x</script>Titre']]],
            [['type' => 'text', 'data' => ['html' => '<p onclick="x()">Texte</p>']], ['type' => 'image', 'data' => ['media' => '12']]],
        ],
    ]]);

    expect($blocks)->toHaveCount(1)->and($blocks[0]['children'])->toHaveCount(2)
        ->and($blocks[0]['data']['ratio'])->toBe('1:2')
        ->and($blocks[0]['children'][0][0]['data']['text'])->not->toContain('<')
        ->and($blocks[0]['children'][1][0]['data']['html'])->not->toContain('onclick')
        ->and($blocks[0]['children'][1][1]['data']['media'])->toBe(12);
    expect(NewsletterBlocks::mediaIds($blocks))->toBe([12]);
});

it('fixes the number of columns, their proportions, and merges surplus columns without losing a block', function () {
    $blocks = NewsletterBlocks::normalize([[
        'type' => 'columns', 'data' => ['count' => 2, 'ratio' => '1:1:1'],
        'children' => [[['type' => 'divider']], [['type' => 'spacer']], [['type' => 'text'], ['type' => 'heading']]],
    ]]);

    expect($blocks[0]['children'])->toHaveCount(2)
        ->and($blocks[0]['data']['ratio'])->toBe('1:1') // des proportions à trois colonnes ne vont pas à deux
        ->and(array_column($blocks[0]['children'][1], 'type'))->toBe(['spacer', 'text', 'heading']);

    $three = NewsletterBlocks::normalize([['type' => 'columns', 'data' => ['count' => 3], 'children' => [[['type' => 'text']]]]]);
    expect($three[0]['children'])->toHaveCount(3)->and($three[0]['children'][2])->toBe([]);
});

it('allows a section to hold columns but no container deeper than that', function () {
    $blocks = NewsletterBlocks::normalize([[
        'type' => 'section', 'children' => [[[
            'type' => 'columns', 'children' => [[['type' => 'section', 'children' => [[['type' => 'text']]]], ['type' => 'text']], []],
        ]]],
    ]]);

    $columns = $blocks[0]['children'][0][0];
    expect($columns['type'])->toBe('columns')
        ->and(array_column($columns['children'][0], 'type'))->toBe(['text']); // le conteneur du troisième niveau est refusé, le texte reste
});

it('gives every block of the tree its own identifier, even when the browser sends duplicates', function () {
    $blocks = NewsletterBlocks::normalize([
        ['id' => 'sameid0001', 'type' => 'section', 'children' => [[['id' => 'sameid0001', 'type' => 'text']]]],
        ['id' => 'sameid0001', 'type' => 'divider'],
    ]);

    $ids = array_column(NewsletterBlocks::flatten($blocks), 'id');
    expect($ids)->toHaveCount(3)->and(array_unique($ids))->toHaveCount(3);
});

it('caps the total number of blocks, nested ones included', function () {
    $many = array_fill(0, 40, ['type' => 'columns', 'children' => [[['type' => 'text'], ['type' => 'text']], [['type' => 'text']]]]);

    expect(count(NewsletterBlocks::flatten(NewsletterBlocks::normalize($many))))->toBe(NewsletterBlocks::MAX_BLOCKS);
});

it('lets the spacer take a colour, and refuses anything that is not #rrggbb', function () {
    $blocks = NewsletterBlocks::normalize([['type' => 'spacer', 'data' => ['height' => 500, 'color' => '#E1EBFF']], ['type' => 'spacer', 'data' => ['color' => 'red; background:url(x)']]]);

    expect($blocks[0]['data'])->toBe(['height' => 160, 'color' => '#e1ebff'])->and($blocks[1]['data']['color'])->toBe('');
});

it('draws columns with exact pixel widths, side by side, with a gap that disappears on mobile', function () {
    $block = NewsletterBlocks::make('columns');
    $html = NewsletterRenderer::html(['subject' => 'x', 'blocks' => [$block], 'theme' => NewsletterBlocks::defaultTheme()]);

    expect($html)->toContain('class="nl-col"')->toContain('class="nl-gap"')->toContain('.nl-gap { display: none !important; }')
        ->and(substr_count($html, 'class="nl-col"'))->toBe(2)
        ->and($html)->not->toContain('data-nl-');
});

it('turns the text light on a dark banner, and leaves it alone on a light one', function () {
    $dark = NewsletterBlocks::normalize([['type' => 'section', 'data' => ['bg' => '#131a3f'], 'children' => [[['type' => 'text']]]]]);
    $light = NewsletterBlocks::normalize([['type' => 'section', 'data' => ['bg' => '#f4f1ea'], 'children' => [[['type' => 'text']]]]]);
    $theme = NewsletterBlocks::defaultTheme();

    expect(NewsletterRenderer::html(['blocks' => $dark, 'theme' => $theme]))->toContain('color:#f1ede4')->toContain('nl-dark')
        ->and(NewsletterRenderer::html(['blocks' => $light, 'theme' => $theme]))->not->toContain('color:#f1ede4');
    expect(NewsletterRenderer::isDark('#131a3f'))->toBeTrue()->and(NewsletterRenderer::isDark('#ffffff'))->toBeFalse()->and(NewsletterRenderer::isDark('red'))->toBeFalse();
});

it('shows the editing marks and drop zones of nested blocks only while editing', function () {
    $state = ['blocks' => [NewsletterBlocks::make('columns')], 'theme' => NewsletterBlocks::defaultTheme()];
    $editing = NewsletterRenderer::html($state, null, null, true);

    expect($editing)->toContain('data-nl-list="root"')->toContain('data-nl-slot')->toContain('data-nl-depth="1"')->toContain('data-nl-parent=');
    expect(NewsletterRenderer::html($state))->not->toContain('data-nl-slot')->not->toContain('data-nl-list');
});

it('draws one row per top-level block for the incremental update of the artboard', function () {
    $blocks = NewsletterBlocks::normalize(NewsletterBlocks::starter());
    $rows = NewsletterRenderer::rows($blocks, NewsletterBlocks::defaultTheme());

    expect($rows)->toHaveCount(3);
    foreach ($rows as $i => $row) {
        expect(trim($row))->toStartWith('<tr data-nl-row')->and($row)->toContain('data-nl-id="'.$blocks[$i]['id'].'"');
    }
});

it('includes the blocks of columns and banners in the plain-text version', function () {
    $blocks = NewsletterBlocks::normalize([['type' => 'section', 'children' => [[['type' => 'heading', 'data' => ['text' => 'Bienvenue']], ['type' => 'columns', 'children' => [[['type' => 'text', 'data' => ['html' => '<p>Dans la colonne</p>']]], []]]]]]]);
    $text = NewsletterRenderer::text(['subject' => 'x', 'blocks' => $blocks]);

    expect($text)->toContain('BIENVENUE')->toContain('Dans la colonne');
});

it('can place a block of every type, composition included, and each survives normalisation unchanged', function () {
    foreach (array_keys(NewsletterBlocks::types()) as $type) {
        $block = NewsletterBlocks::make($type);
        expect(NewsletterBlocks::normalize([$block]))->toBe([$block]);
    }

    foreach (NewsletterBlocks::presets() as $key => $preset) {
        $block = NewsletterBlocks::withIds([$preset['block']])[0];
        expect(NewsletterBlocks::normalize([$block]))->toBe([$block], "composition {$key}");
    }
});

// --- Modèles ----------------------------------------------------------------------------------------------------------------------------

it('offers modern templates that build, normalise without loss and draw without error', function () {
    expect(NewsletterTemplates::keys())->toContain('une', 'recit', 'magazine', 'affiche', 'actualites', 'simple')
        ->and(NewsletterTemplates::build('inconnu'))->toBeNull();

    foreach (NewsletterTemplates::keys() as $key) {
        $built = NewsletterTemplates::build($key);
        expect($built['subject'])->not->toBe('')->and($built['blocks'])->not->toBeEmpty()
            ->and(NewsletterBlocks::normalize($built['blocks']))->toBe($built['blocks'], "modèle {$key}");
        expect(NewsletterRenderer::html($built))->toContain('</html>')->not->toContain('data-nl-');
        expect(NewsletterRenderer::text($built))->not->toBe('');
    }
});

it('fills the photo blocks of a template with the latest photos of the media library', function () {
    $asset = MediaAsset::create(['title' => 'Photo']);
    $asset->addMedia(UploadedFile::fake()->image('campus.jpg', 800, 600))->toMediaCollection(MediaAsset::COLLECTION);

    $built = NewsletterTemplates::build('recit');
    $photos = collect(NewsletterBlocks::flatten($built['blocks']))->where('type', 'image')->pluck('data.media');

    expect($photos->unique()->all())->toBe([$asset->id]);

    // Photo en fond et mosaïque : tous les emplacements affichés reçoivent une photo (les cases en trop, non).
    $une = collect(NewsletterBlocks::flatten(NewsletterTemplates::build('une')['blocks']));
    $mosaic = $une->firstWhere('type', 'mosaic')['data'];
    expect($une->firstWhere('type', 'hero')['data']['media'])->toBe($asset->id)
        ->and([$mosaic['media'], $mosaic['media2'], $mosaic['media3']])->toBe([$asset->id, $asset->id, $asset->id])
        ->and($mosaic['media4'])->toBe(0);
});

it('draws the hero and mosaic blocks as exact-size photos, with parallax, badge and offset shadow', function () {
    $asset = MediaAsset::create(['title' => 'Photo']);
    $asset->addMedia(UploadedFile::fake()->image('campus.jpg', 1200, 800))->toMediaCollection(MediaAsset::COLLECTION);

    $html = NewsletterRenderer::html(NewsletterTemplates::build('une'));
    expect($html)->toContain('background-attachment:fixed')->toContain('newsletter-tiles/')->toContain('v:fill')->toContain('border-radius:50%')
        ->toContain('Nouveau')->toContain('class="nl-tile"');

    $html = NewsletterRenderer::html(NewsletterTemplates::build('recit'));
    expect($html)->toContain('padding:0 18px 18px 0');

    // Dans le plan de travail, une case vide invite à choisir sa photo ; dans le courrier envoyé, rien de tel.
    $empty = ['subject' => 'x', 'theme' => NewsletterBlocks::defaultTheme(), 'blocks' => NewsletterBlocks::normalize(NewsletterBlocks::withIds([NewsletterBlocks::node('mosaic', ['layout' => '2x2']), NewsletterBlocks::node('hero')]))];
    expect(NewsletterRenderer::html($empty, null, null, true))->toContain('data-nl-media="media4"')->toContain('data-nl-media="media"')
        ->and(NewsletterRenderer::html($empty))->not->toContain('data-nl-');
});

it('keeps the tile and hero fields within their limits', function () {
    $blocks = NewsletterBlocks::normalize(NewsletterBlocks::withIds([
        NewsletterBlocks::node('hero', ['layout' => 'nope', 'height' => 9000, 'tone' => 'rose', 'opacity' => 3, 'badge' => '<b>'.str_repeat('x', 80).'</b>']),
        NewsletterBlocks::node('mosaic', ['layout' => 'nope', 'gap' => 99, 'frame' => 'javascript:1']),
    ]));

    expect($blocks[0]['data']['layout'])->toBe('left')->and($blocks[0]['data']['height'])->toBeLessThanOrEqual(560)->and($blocks[0]['data']['tone'])->toBe('navy')
        ->and(mb_strlen($blocks[0]['data']['badge']))->toBeLessThanOrEqual(24)->and($blocks[0]['data']['badge'])->not->toContain('<')
        ->and($blocks[1]['data']['layout'])->toBe('1-2')->and($blocks[1]['data']['gap'])->toBeLessThanOrEqual(14)->and($blocks[1]['data']['frame'])->toBe('');
});

it('creates a letter from the chosen template', function () {
    $this->actingAs($this->editor);

    Livewire::test(CreateNewsletter::class)->fillForm(['subject' => 'Ma lettre', 'template' => 'actualites'])->call('create')->assertHasNoFormErrors();

    $letter = Newsletter::sole();
    expect(collect(NewsletterBlocks::flatten($letter->blocks))->pluck('type'))->toContain('columns', 'section')
        ->and($letter->theme['font'])->toBe('sans');
});

// --- Points d'accès de l'éditeur --------------------------------------------------------------------------------------------------------

it('draws rows on request, normalised, for editors only', function () {
    $letter = draftLetter();
    $payload = ['blocks' => [['id' => 'rowtest001', 'type' => 'heading', 'data' => ['text' => '<b>Un titre</b>']], ['type' => 'nope']], 'theme' => NewsletterBlocks::defaultTheme()];

    $this->postJson(route('admin.newsletter-editor.rows', $letter), $payload)->assertUnauthorized();
    $this->actingAs(User::factory()->create())->postJson(route('admin.newsletter-editor.rows', $letter), $payload)->assertForbidden();

    $rows = $this->actingAs($this->editor)->postJson(route('admin.newsletter-editor.rows', $letter), $payload)->assertOk()->json('rows');
    expect($rows)->toHaveCount(1)->and($rows[0])->toContain('data-nl-id="rowtest001"')->not->toContain('<b>');

    $this->actingAs($this->editor)->postJson(route('admin.newsletter-editor.rows', $letter), ['blocks' => array_fill(0, 41, ['type' => 'divider'])])->assertUnprocessable();
});

it('lists the templates with a preview, blocks and theme, for editors only', function () {
    $letter = draftLetter();

    $this->getJson(route('admin.newsletter-editor.templates', $letter))->assertUnauthorized();
    $this->actingAs(User::factory()->create())->getJson(route('admin.newsletter-editor.templates', $letter))->assertForbidden();

    $templates = $this->actingAs($this->editor)->getJson(route('admin.newsletter-editor.templates', $letter))->assertOk()->json('templates');
    expect($templates)->toHaveCount(count(NewsletterTemplates::keys()))
        ->and($templates[0])->toHaveKeys(['key', 'name', 'description', 'subject', 'theme', 'blocks', 'preview', 'media'])
        ->and($templates[0]['preview'])->toContain('<html');
});

it('gives the editor the block library, with ready-made compositions, and the sender address', function () {
    $html = $this->actingAs($this->editor)->get(route('admin.newsletter-editor.show', draftLetter()))->assertOk()->getContent();

    expect($html)->toContain('"photo-text"')->toContain('"banner-dark"')->toContain('newsletter@zurcher.edu.mg');
});

it('sends the letters from the dedicated newsletter address, replies going to the site address', function () {
    $envelope = (new NewsletterMessage(draftLetter(), subscriber('un@exemple.org')))->envelope();

    expect($envelope->from->address)->toBe('newsletter@zurcher.edu.mg')->and($envelope->from->name)->toBe('Université Adventiste Zurcher');

    config(['newsletter.from.address' => 'autre@zurcher.edu.mg']);
    expect((new NewsletterMessage(draftLetter(), null, test: true))->envelope()->from->address)->toBe('autre@zurcher.edu.mg');
});

// --- Images des courriers --------------------------------------------------------------------------------------------------------------

/** Le message tel que Symfony l'enverrait (transport « array » des tests). */
function sentEmail(NewsletterMessage $mail): Email
{
    $mailer = Mail::mailer('array');
    $mailer->to('moi@exemple.org')->send($mail);

    return $mailer->getSymfonyTransport()->messages()->last()->getOriginalMessage();
}

it('attaches the images of the site to the message when the site is local, so they show up in the mailbox', function () {
    $letter = draftLetter(['theme' => ['header' => '1'] + NewsletterBlocks::modernHeader(['facebook' => 'https://facebook.com/uaz'])]);
    $email = sentEmail(new NewsletterMessage($letter, null, test: true));
    $html = $email->getHtmlBody();

    expect($html)->toContain('src="cid:')->not->toMatch('~src="https?://(localhost|127\.0\.0\.1)~')
        ->and(preg_match_all('/^Content-ID:/mi', $email->toString()))->toBeGreaterThanOrEqual(2); // le sceau et l'icône Facebook
    expect($html)->toContain('href="https://facebook.com/uaz"'); // les liens ne sont pas touchés
});

it('leaves the image addresses alone when attaching is switched off or the site is public', function () {
    $letter = draftLetter();

    config(['newsletter.embed_images' => false]);
    $email = sentEmail(new NewsletterMessage($letter, null, test: true));
    expect($email->getHtmlBody())->toContain('src="http')->not->toContain('src="cid:')->and($email->getAttachments())->toBe([]);

    config(['newsletter.embed_images' => null, 'app.url' => 'https://test.zurcher.edu.mg']);
    URL::forceRootUrl('https://test.zurcher.edu.mg');
    expect(sentEmail(new NewsletterMessage($letter, null, test: true))->getHtmlBody())->not->toContain('src="cid:');
    URL::forceRootUrl(null);

    config(['newsletter.embed_images' => true]);
    expect(sentEmail(new NewsletterMessage($letter, null, test: true))->getHtmlBody())->toContain('src="cid:');
});

it('makes a JPEG for the letters when a photo is added to the library, and falls back to WebP before it exists', function () {
    $asset = MediaAsset::create(['title' => 'Photo']);
    $asset->addMedia(UploadedFile::fake()->image('campus.jpg', 1600, 900))->toMediaCollection(MediaAsset::COLLECTION);
    $file = $asset->fresh()->file();

    expect($file->hasGeneratedConversion('mail'))->toBeTrue()
        ->and(NewsletterRenderer::imageUrl($asset->fresh()))->toEndWith('-mail.jpg');

    $file->forceFill(['generated_conversions' => ['card' => true]])->save();
    expect(NewsletterRenderer::imageUrl($asset->fresh()))->toEndWith('-card.webp');
});

it('lays the embedded images out the way mail clients expect, with no file name, so Gmail does not list them as attachments', function () {
    $raw = sentEmail(new NewsletterMessage(draftLetter(), null, test: true))->toString();

    // « texte brut OU (HTML + images) » : les images sont rangées avec le HTML, pas à côté.
    expect($raw)->toMatch('~Content-Type: multipart/alternative.*?Content-Type: text/plain.*?Content-Type: multipart/related.*?Content-Type: text/html.*?Content-Type: image/~s')
        ->and($raw)->not->toMatch('~^Content-Disposition: [^\n]*filename~mi')->not->toMatch('~^Content-Disposition: attachment~mi')
        ->and(preg_match_all('~^Content-Disposition: inline~mi', $raw))->toBe(preg_match_all('~^Content-ID:~mi', $raw));
});

it('offers every template as a ready-made set of blocks in the add panel, photos included in the media map', function () {
    $asset = MediaAsset::create(['title' => 'Photo']);
    $asset->addMedia(UploadedFile::fake()->image('campus.jpg', 800, 600))->toMediaCollection(MediaAsset::COLLECTION);

    $html = $this->actingAs($this->editor)->get(route('admin.newsletter-editor.show', draftLetter()))->assertOk()->getContent();

    expect($html)->toContain('"kind":"template"');
    foreach (NewsletterTemplates::keys() as $key) {
        expect($html)->toContain('"key":"'.$key.'"');
    }

    // Les photos posées par les modèles sont connues de l'éditeur (miniatures du panneau de réglages) même quand la newsletter n'en contient pas.
    expect($html)->toContain('"media":{"'.$asset->id.'"');
});

it('keeps a text field the editor emptied (a button without label stays without button) instead of restoring the default', function () {
    $letter = draftLetter();
    $state = letterState([['type' => 'hero', 'data' => ['button_label' => '', 'eyebrow' => '', 'title' => 'Titre']]]);

    // Laravel convertit '' en null dans une requête JSON : le serveur doit comprendre « vidé », pas « absent ».
    $this->actingAs($this->editor)->putJson(route('admin.newsletter-editor.save', $letter), $state)->assertOk();

    $data = $letter->refresh()->blocks[0]['data'];
    expect($data['button_label'])->toBe('')->and($data['eyebrow'])->toBe('')->and($data['title'])->toBe('Titre')
        ->and(NewsletterRenderer::html(['subject' => 'x', 'theme' => NewsletterBlocks::defaultTheme(), 'blocks' => $letter->blocks]))->not->toContain('En savoir plus');

    // Un champ absent de la requête reprend bien sa valeur par défaut.
    $this->actingAs($this->editor)->putJson(route('admin.newsletter-editor.save', $letter), letterState([['type' => 'hero', 'data' => ['title' => 'Titre']]]))->assertOk();
    expect($letter->refresh()->blocks[0]['data']['button_label'])->toBe('En savoir plus');
});

it('gives the test message an unsubscribe link that works but unsubscribes nobody', function () {
    $active = subscriber('actif@exemple.test');
    $mail = new NewsletterMessage(draftLetter(), null, test: true);

    $mail->assertSeeInHtml('/newsletter/desinscription-essai?signature=');
    $mail->assertDontSeeInHtml('le lien personnel est ajouté');
    expect($mail->headers()->text['List-Unsubscribe'])->toContain('/newsletter/desinscription-essai');

    $url = URL::signedRoute('newsletter.unsubscribe.test');
    $this->get($url)->assertOk()->assertSee('Envoi d’essai')->assertSee('n’a désinscrit personne');
    expect($active->refresh()->unsubscribed_at)->toBeNull();

    // Sans signature valide, le lien est refusé.
    $this->get(route('newsletter.unsubscribe.test'))->assertForbidden();
});

it('unsubscribes the subscriber from the personal link of a real message', function () {
    $sub = subscriber('vrai@exemple.test');
    $mail = new NewsletterMessage(draftLetter(), $sub);
    preg_match('#href="([^"]*desinscription/'.$sub->id.'[^"]*)"#', $mail->render(), $match);

    $this->get(html_entity_decode($match[1]))->assertOk()->assertSee('Désinscription effectuée');
    expect($sub->refresh()->unsubscribed_at)->not->toBeNull();
});
