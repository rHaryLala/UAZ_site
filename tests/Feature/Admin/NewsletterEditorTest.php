<?php

use App\Filament\Resources\Newsletters\Pages\CreateNewsletter;
use App\Filament\Resources\Newsletters\Pages\ListNewsletters;
use App\Jobs\SendNewsletter;
use App\Jobs\SendNewsletterMessage;
use App\Mail\NewsletterMessage;
use App\Models\Newsletter;
use App\Models\NewsletterDelivery;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use App\Support\NewsletterBlocks;
use App\Support\NewsletterRenderer;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

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
