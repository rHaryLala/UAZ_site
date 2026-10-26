<?php

use App\Models\Redirect;
use Illuminate\Support\Facades\Cache;

it('normalizes paths', function (string $input, string $expected) {
    expect(Redirect::normalize($input))->toBe($expected);
})->with([
    ['/a-propos/', '/a-propos'],
    ['a-propos', '/a-propos'],
    ['https://zurcher.edu.mg/blog/', '/blog'],
    ['https://zurcher.edu.mg', '/'],
    ['/', '/'],
    ['/page/#ancre', '/page'],
]);

it('redirects an old URL with a 301 and counts the hit', function () {
    $redirect = Redirect::factory()->create(['from_path' => '/blog/', 'to_path' => '/actualites']);

    $this->get('/blog/')->assertStatus(301)->assertRedirect('/actualites');

    expect($redirect->fresh()->hits)->toBe(1)->and($redirect->fresh()->last_hit_at)->not->toBeNull();
});

it('ignores inactive redirects and does not redirect to itself', function () {
    Redirect::factory()->create(['from_path' => '/inactive', 'to_path' => '/x', 'is_active' => false]);
    Redirect::factory()->create(['from_path' => '/boucle', 'to_path' => '/boucle/']);

    $this->get('/inactive')->assertStatus(404);
    $this->get('/boucle')->assertStatus(404);
});

it('supports temporary 302 redirects', function () {
    Redirect::factory()->create(['from_path' => '/provisoire', 'to_path' => '/ailleurs', 'status_code' => 302]);

    $this->get('/provisoire')->assertStatus(302);
});

it('maps the legacy WordPress search to the new search page', function () {
    $this->get('/?s=licence+droit')->assertStatus(301)->assertRedirect('/recherche?q=licence+droit');
});

it('never intercepts the back-office', function () {
    Redirect::factory()->create(['from_path' => '/admin/login', 'to_path' => '/']);

    $this->get('/admin/login')->assertOk();
});

it('refreshes the cached map when a redirect changes', function () {
    Redirect::factory()->create(['from_path' => '/ancien', 'to_path' => '/nouveau']);
    expect(Redirect::map())->toHaveKey('/ancien');

    Redirect::query()->where('from_path', '/ancien')->first()->delete();

    expect(Cache::has(Redirect::CACHE_KEY))->toBeFalse()->and(Redirect::map())->not->toHaveKey('/ancien');
});
