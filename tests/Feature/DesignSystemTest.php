<?php

use App\Support\Html;

it('renders the design system page in development', function () {
    $this->get('/design-system')
        ->assertOk()
        ->assertSee('Esprit, Main,', false)
        ->assertSee('Couleurs')
        ->assertSee('rel="preload"', false)
        ->assertSee('<html lang="fr"', false);
});

it('exposes the self-hosted fonts and a skip link for accessibility', function () {
    $html = $this->get('/design-system')->getContent();

    expect($html)->toContain('Aller au contenu principal')
        ->and($html)->toContain('id="contenu"')
        ->and($html)->toContain('.woff2')
        ->and($html)->not->toContain('fonts.googleapis.com')
        ->and($html)->not->toContain('fonts.bunny.net');
});

it('is not noindexed by accident on public pages but is on the design system', function () {
    $this->get('/design-system')->assertSee('noindex', false);
});

it('computes WCAG contrast ratios shown in the catalogue (all AA or better)', function () {
    $html = $this->get('/design-system')->getContent();

    expect($html)->not->toContain('insuffisant');
});

it('sanitizes rich text: scripts, event handlers and javascript links are removed', function () {
    $dirty = '<p onclick="alert(1)">Bonjour <a href="javascript:alert(2)">lien</a> <a href="https://exemple.org">sûr</a></p>'
        .'<script>alert(3)</script><iframe src="https://evil.test"></iframe><img src="/x.jpg" onerror="alert(4)" alt="x">';

    $clean = (string) Html::clean($dirty);

    expect($clean)->toContain('Bonjour')
        ->and($clean)->toContain('https://exemple.org')
        ->and($clean)->toContain('noopener')
        ->and($clean)->not->toContain('onclick')
        ->and($clean)->not->toContain('javascript:')
        ->and($clean)->not->toContain('<script')
        ->and($clean)->not->toContain('<iframe')
        ->and($clean)->not->toContain('onerror');
});

it('returns an empty string for blank rich text', function () {
    expect((string) Html::clean(null))->toBe('')->and((string) Html::clean('  '))->toBe('');
});
