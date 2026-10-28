<?php

use App\Mail\NewsletterConfirmation;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\StructureSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

beforeEach(fn () => Mail::fake());

it('registers a pending subscriber and sends a confirmation e-mail', function () {
    $this->postJson('/newsletter', ['email' => 'Visiteur@Exemple.com'])
        ->assertOk()
        ->assertJsonPath('message', fn ($m) => str_contains($m, 'confirmation'));

    $subscriber = NewsletterSubscriber::sole();

    expect($subscriber->email)->toBe('visiteur@exemple.com')->and($subscriber->status())->toBe('pending');
    Mail::assertSent(NewsletterConfirmation::class, fn ($mail) => $mail->hasTo('visiteur@exemple.com'));
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

it('does not send a second e-mail right away, nor to an already active subscriber', function () {
    $this->postJson('/newsletter', ['email' => 'a@exemple.com'])->assertOk();
    $this->postJson('/newsletter', ['email' => 'a@exemple.com'])->assertOk();
    Mail::assertSentCount(1);

    NewsletterSubscriber::sole()->update(['confirmed_at' => now(), 'confirmation_sent_at' => now()->subHour()]);
    $this->postJson('/newsletter', ['email' => 'a@exemple.com'])->assertOk();

    Mail::assertSentCount(1);
    expect(NewsletterSubscriber::sole()->status())->toBe('active');
});

it('confirms and unsubscribes only through signed links', function () {
    $subscriber = NewsletterSubscriber::create(['email' => 'b@exemple.com', 'confirmation_sent_at' => now()]);

    $this->get(route('newsletter.confirm', $subscriber))->assertForbidden();
    $this->get(URL::temporarySignedRoute('newsletter.confirm', now()->addDay(), ['subscriber' => $subscriber]))->assertOk()->assertSee('Abonnement confirmé');
    expect($subscriber->fresh()->status())->toBe('active');

    $this->get(route('newsletter.unsubscribe', $subscriber))->assertForbidden();
    $this->get(URL::signedRoute('newsletter.unsubscribe', ['subscriber' => $subscriber]))->assertOk()->assertSee('Désinscription effectuée');
    expect($subscriber->fresh()->status())->toBe('unsubscribed');
});

it('lets a former subscriber subscribe again', function () {
    NewsletterSubscriber::create(['email' => 'c@exemple.com', 'confirmed_at' => now()->subYear(), 'unsubscribed_at' => now()->subMonth()]);

    $this->postJson('/newsletter', ['email' => 'c@exemple.com'])->assertOk();

    expect(NewsletterSubscriber::sole()->status())->toBe('pending');
    Mail::assertSentCount(1);
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
    \Livewire\Livewire::test(\App\Filament\Resources\NewsletterSubscribers\Pages\ListNewsletterSubscribers::class)->loadTable()->assertCanSeeTableRecords([$subscriber]);
});
