<?php

use App\Filament\Resources\NewsletterSubscribers\Pages\ListNewsletterSubscribers;
use App\Models\Menu;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\StructureSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

beforeEach(fn () => Mail::fake());

it('subscribes the address right away, without sending any e-mail', function () {
    $this->postJson('/newsletter', ['email' => 'Visiteur@Exemple.com'])
        ->assertOk()
        ->assertJsonPath('message', fn ($m) => str_contains($m, 'abonné') && ! str_contains($m, 'confirmation'));

    $subscriber = NewsletterSubscriber::sole();

    expect($subscriber->email)->toBe('visiteur@exemple.com')->and($subscriber->status())->toBe('active')
        ->and($subscriber->confirmed_at)->not->toBeNull()->and($subscriber->confirmation_sent_at)->toBeNull()
        ->and(NewsletterSubscriber::active()->pluck('email')->all())->toBe(['visiteur@exemple.com']);

    Mail::assertNothingSent();
    Mail::assertNothingQueued();
});

it('does not even need a working mail server', function () {
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'serveur-inexistant.invalid']);

    $this->postJson('/newsletter', ['email' => 'sans-courrier@exemple.com'])->assertOk();

    expect(NewsletterSubscriber::sole()->status())->toBe('active');
});

it('rejects an invalid address', function () {
    $this->postJson('/newsletter', ['email' => 'pas-une-adresse'])->assertUnprocessable()->assertJsonValidationErrors('email');
    $this->postJson('/newsletter', [])->assertUnprocessable();

    expect(NewsletterSubscriber::count())->toBe(0);
});

it('ignores robots that fill the hidden field', function () {
    $this->postJson('/newsletter', ['email' => 'robot@exemple.com', 'website' => 'http://spam.example'])->assertOk();

    expect(NewsletterSubscriber::count())->toBe(0);
    Mail::assertNothingSent();
});

it('answers the same way, and changes nothing, when the address is already subscribed', function () {
    $this->postJson('/newsletter', ['email' => 'a@exemple.com'])->assertOk();
    $first = NewsletterSubscriber::sole()->confirmed_at;

    $this->travel(2)->days();
    $this->postJson('/newsletter', ['email' => 'A@exemple.com'])->assertOk();

    expect(NewsletterSubscriber::count())->toBe(1)->and(NewsletterSubscriber::sole()->confirmed_at->equalTo($first))->toBeTrue();
});

it('unsubscribes only through a signed link', function () {
    $subscriber = NewsletterSubscriber::create(['email' => 'b@exemple.com', 'confirmed_at' => now()]);

    $this->get(route('newsletter.unsubscribe', $subscriber))->assertForbidden();
    $this->get(URL::signedRoute('newsletter.unsubscribe', ['subscriber' => $subscriber]))->assertOk()->assertSee('Désinscription effectuée');

    expect($subscriber->fresh()->status())->toBe('unsubscribed');
});

it('has no confirmation link any more', function () {
    expect(Route::has('newsletter.confirm'))->toBeFalse()->and(Route::has('newsletter.unsubscribe'))->toBeTrue();
});

it('lets a former subscriber subscribe again with a new request', function () {
    NewsletterSubscriber::create(['email' => 'c@exemple.com', 'confirmed_at' => now()->subYear(), 'unsubscribed_at' => now()->subMonth()]);

    $this->postJson('/newsletter', ['email' => 'c@exemple.com'])->assertOk();

    $subscriber = NewsletterSubscriber::sole();
    expect($subscriber->status())->toBe('active')->and($subscriber->unsubscribed_at)->toBeNull();
});

it('turns an unconfirmed address (imported from MailPoet) into a subscriber when its owner asks', function () {
    NewsletterSubscriber::create(['email' => 'attente@exemple.com']);

    expect(NewsletterSubscriber::active()->count())->toBe(0);

    $this->postJson('/newsletter', ['email' => 'attente@exemple.com'])->assertOk();

    expect(NewsletterSubscriber::sole()->status())->toBe('active');
});

it('shows the dialog without announcing a confirmation e-mail', function () {
    $this->seed(StructureSeeder::class);

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('data-newsletter-dialog')->toContain('Nous n&#039;utilisons votre adresse que pour cette lettre.')
        ->not->toContain('Un e-mail de confirmation vous sera envoyé');

    $this->get('/en')->assertOk()->assertSee('We only use your address for this newsletter.')->assertDontSee('confirmation email will be sent');
});
it('limits the number of requests per minute', function () {
    foreach (range(1, 6) as $i) {
        $this->postJson('/newsletter', ['email' => "n$i@exemple.com"])->assertOk();
    }

    $this->postJson('/newsletter', ['email' => 'n7@exemple.com'])->assertStatus(429);
});

it('shows the dialog on public pages and "S\'abonner" right after "Yearbook" in the top bar', function () {
    $this->seed(StructureSeeder::class);

    $html = $this->get('/')->assertOk()->assertSee('data-newsletter-dialog', false)->getContent();

    expect($html)->toContain('data-newsletter-open')
        ->and(strpos($html, 'Yearbook'))->toBeLessThan(strpos($html, 'data-newsletter-open'));
});

it('lists subscribers in the admin only for people allowed to read messages', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    NewsletterSubscriber::create(['email' => 'liste@exemple.com', 'confirmed_at' => now()]);

    $this->actingAs(User::factory()->create())->get('/admin/newsletter-subscribers')->assertForbidden();

    $this->actingAs(tap(User::factory()->create())->assignRole(RolesAndPermissionsSeeder::SUPER_ADMIN));
    $this->get('/admin/newsletter-subscribers')->assertOk();

    $subscriber = NewsletterSubscriber::sole();
    Livewire::test(ListNewsletterSubscribers::class)->loadTable()->assertCanSeeTableRecords([$subscriber]);
});

it('puts "Actualités" right after "S\'abonner" in the top bar', function () {
    $this->seed(StructureSeeder::class);

    $labels = collect(Menu::where('handle', 'topbar')->firstOrFail()->items)->pluck('label')->all();

    expect(array_search('Actualités', $labels))->toBe(array_search('S\'abonner', $labels) + 1);
});
