<?php

use App\Enums\PublicationStatus;
use App\Models\Page;
use Database\Seeders\StructureSeeder;

beforeEach(fn () => $this->seed(StructureSeeder::class));

it('creates the privacy page as a draft that visitors cannot see yet', function () {
    $page = Page::where('key', 'privacy')->firstOrFail();

    expect($page->status)->toBe(PublicationStatus::Draft)->and($page->path)->toBe('confidentialite');

    $this->get('/confidentialite')->assertNotFound();
    $this->get('/')->assertOk()->assertDontSee('Confidentialité et cookies');
});

it('links the privacy page from the footer once it is published and describes the real cookies', function () {
    Page::where('key', 'privacy')->firstOrFail()->update(['status' => PublicationStatus::Published, 'published_at' => now()->subMinute()]);

    $this->get('/')->assertOk()->assertSee('Confidentialité et cookies')->assertSee('/confidentialite', false);

    $this->get('/confidentialite')->assertOk()
        ->assertSee('universite-adventiste-zurcher-session')
        ->assertSee('XSRF-TOKEN')
        ->assertSee('aucun cookie publicitaire');
});

it('keeps the cookie names written on the page in line with the real configuration', function () {
    expect(config('session.cookie'))->toBe('universite-adventiste-zurcher-session')
        ->and((int) config('session.lifetime'))->toBe(120);
});

it('does not duplicate the page when the seeder runs again', function () {
    $this->seed(StructureSeeder::class);

    expect(Page::where('key', 'privacy')->count())->toBe(1);
});
