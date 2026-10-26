<?php

use App\Livewire\ContactForm;
use App\Livewire\Search;
use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use App\Models\Event;
use App\Models\Formation;
use App\Models\NewsPost;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

beforeEach(function () {
    RateLimiter::clear('contact:127.0.0.1');
    Mail::fake();
    config(['uaz.contact.recipient' => 'bureau@example.org']);
});

function fillContact($component, array $overrides = [])
{
    return $component
        ->set('renderedAt', time() - 30) // le visiteur a pris le temps de remplir le formulaire
        ->set('name', $overrides['name'] ?? 'Aina Rakoto')
        ->set('email', $overrides['email'] ?? 'aina@example.org')
        ->set('phone', $overrides['phone'] ?? '+261 34 00 000 00')
        ->set('subject', $overrides['subject'] ?? 'Admissions 2027')
        ->set('message', $overrides['message'] ?? 'Bonjour, je souhaite connaître les conditions d\'admission.');
}

it('stores the message, notifies the office and confirms to the visitor', function () {
    fillContact(Livewire::test(ContactForm::class))
        ->call('send')
        ->assertHasNoErrors()
        ->assertSet('sent', true)
        ->assertSee('Merci, votre message a bien été envoyé.');

    $message = ContactMessage::sole();
    expect($message->email)->toBe('aina@example.org')
        ->and($message->ip_hash)->toHaveLength(64)
        ->and($message->ip_hash)->not->toContain('127.0.0.1');

    Mail::assertSent(ContactMessageReceived::class, fn ($mail) => $mail->hasTo('bureau@example.org') && $mail->hasReplyTo('aina@example.org'));
});

it('falls back to the site e-mail address when no recipient is configured', function () {
    config(['uaz.contact.recipient' => null]);
    SiteSetting::current()->update(['email' => 'office@example.org']);

    fillContact(Livewire::test(ContactForm::class))->call('send');

    Mail::assertSent(ContactMessageReceived::class, fn ($mail) => $mail->hasTo('office@example.org'));
});

it('validates every field with French messages', function () {
    Livewire::test(ContactForm::class)
        ->set('renderedAt', time() - 30)
        ->set('email', 'pas-un-email')
        ->set('phone', 'abc')
        ->call('send')
        ->assertHasErrors(['name' => 'required', 'email' => 'email', 'phone' => 'regex', 'subject' => 'required', 'message' => 'required'])
        ->assertSee('Le champ nom est obligatoire.')
        ->assertSee('Le champ adresse e-mail doit être une adresse e-mail valide.');

    expect(ContactMessage::count())->toBe(0);
});

it('rejects overly long messages', function () {
    fillContact(Livewire::test(ContactForm::class), ['message' => str_repeat('a', 5001)])
        ->call('send')
        ->assertHasErrors(['message' => 'max']);
});

it('silently drops submissions that fill the honeypot', function () {
    fillContact(Livewire::test(ContactForm::class))
        ->set('website', 'http://spam.example')
        ->call('send')
        ->assertSet('sent', true);

    expect(ContactMessage::count())->toBe(0);
    Mail::assertNothingSent();
});

it('silently drops submissions made faster than a human can type', function () {
    Livewire::test(ContactForm::class)
        ->set('renderedAt', time())
        ->set('name', 'Robot')->set('email', 'bot@example.org')->set('subject', 'Offre spéciale')
        ->set('message', 'Achetez maintenant, offre limitée dans le temps.')
        ->call('send')
        ->assertSet('sent', true);

    expect(ContactMessage::count())->toBe(0);
});

it('rate-limits repeated submissions from the same address', function () {
    $limit = config('uaz.contact.rate_limit_per_minute');

    foreach (range(1, $limit) as $i) {
        fillContact(Livewire::test(ContactForm::class))->call('send')->assertSet('sent', true);
    }

    fillContact(Livewire::test(ContactForm::class))
        ->call('send')
        ->assertHasErrors('message')
        ->assertSet('sent', false);

    expect(ContactMessage::count())->toBe($limit);
});

it('prefills the subject from the query string', function () {
    Livewire::withQueryParams(['sujet' => 'Formation : Licence en Droit'])
        ->test(ContactForm::class)
        ->assertSet('subject', 'Formation : Licence en Droit');
});

it('finds published content across sections', function () {
    Formation::factory()->create(['title' => 'Licence en Agroécologie']);
    NewsPost::factory()->create(['title' => 'Journée agroécologie au campus']);
    Event::factory()->create(['title' => 'Atelier agroécologie']);

    Livewire::withQueryParams(['q' => 'agroécologie'])
        ->test(Search::class)
        ->assertSee('Licence en Agroécologie')
        ->assertSee('Journée agroécologie au campus')
        ->assertSee('Atelier agroécologie')
        ->assertSee('Formations')->assertSee('Actualités')->assertSee('Événements');
});

it('does not search drafts or scheduled content', function () {
    NewsPost::factory()->draft()->create(['title' => 'Brouillon confidentiel']);
    NewsPost::factory()->scheduled()->create(['title' => 'Annonce programmée']);

    Livewire::test(Search::class)->set('query', 'confidentiel')->assertSee('Aucun résultat');
    Livewire::test(Search::class)->set('query', 'programmée')->assertSee('Aucun résultat');
});

it('asks for at least two characters and escapes wildcard characters', function () {
    NewsPost::factory()->create(['title' => 'Cent pour cent réussite']);

    Livewire::test(Search::class)->set('query', 'a')->assertSee('au moins 2 caractères');
    Livewire::test(Search::class)->set('query', '%%')->assertSee('Aucun résultat');
});

it('renders the search page without JavaScript through a GET form', function () {
    NewsPost::factory()->create(['title' => 'Rentrée solennelle']);

    $this->get('/recherche?q=solennelle')->assertOk()->assertSee('Rentrée solennelle')->assertSee('role="search"', false);
});
