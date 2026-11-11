<?php

use App\Models\Newsletter;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\NewsletterBlocks;
use App\Support\NewsletterRenderer;
use App\Support\NewsletterTemplates;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->editor = User::factory()->create()->assignRole(RolesAndPermissionsSeeder::EDITOR);
});

/** La lettre telle que l'envoi la dessine, avec ces réglages d'en-tête. */
function sentHeader(array $theme = []): string
{
    return NewsletterRenderer::header(NewsletterBlocks::theme($theme + NewsletterBlocks::defaultTheme()));
}

it('keeps the old header for letters written before the header settings existed', function () {
    $html = NewsletterRenderer::header(NewsletterBlocks::theme(['header' => '1', 'header_color' => '#131a3f']));

    expect($html)->toContain('uaz-seal-384.png')->toContain('Université Adventiste')
        ->not->toContain('#UAZINFOS')->not->toContain('facebook')
        ->and($html)->toContain('height:3px'); // le filet doré
});

it('draws the UAZ header : hashtag on the left, seal in the middle, social icons on the right', function () {
    $theme = NewsletterBlocks::modernHeader(['facebook' => 'https://facebook.com/uaz', 'youtube' => 'https://youtube.com/uaz']);
    $html = NewsletterRenderer::header(NewsletterBlocks::theme($theme));

    expect($html)->toContain('#UAZINFOS')->toContain('uaz-seal-384.png')
        ->toContain('images/newsletter/facebook-white.png')->toContain('images/newsletter/youtube-white.png')
        ->not->toContain('instagram-white.png') // pas d'adresse, pas d'icône
        ->and($html)->toContain('href="https://facebook.com/uaz"')->not->toContain('<br><span')
        ->not->toContain('height:3px'); // pas de filet doré
    expect(strpos($html, '#UAZINFOS'))->toBeLessThan(strpos($html, 'uaz-seal-384.png'))
        ->and(strpos($html, 'uaz-seal-384.png'))->toBeLessThan(strpos($html, 'facebook-white.png'));
});

it('uses dark icons and text on a light header', function () {
    $html = sentHeader(['header_color' => '#ffffff'] + NewsletterBlocks::modernHeader(['facebook' => 'https://facebook.com/uaz']));

    expect($html)->toContain('facebook-dark.png')->not->toContain('facebook-white.png')->toContain('color:#1d2233');
});

it('lets each element be moved or hidden, and resizes the band and the logo', function () {
    $html = sentHeader(['header_logo' => 'right', 'header_tag' => '', 'header_height' => 200, 'header_logo_size' => 90]);

    expect($html)->toContain('height:200px')->toContain('width="90"')->not->toContain('#UAZINFOS')
        ->and($html)->toContain('margin:0 0 0 auto;'); // à droite
    expect(sentHeader(['header_logo' => '', 'header_name' => '']))->not->toContain('uaz-seal-384.png');
});

it('keeps the settings within bounds and refuses anything unsafe', function () {
    $theme = NewsletterBlocks::theme([
        'header_logo' => 'top', 'header_height' => 9999, 'header_logo_size' => 1, 'header_tag_text' => '<b>#Tag</b>'.str_repeat('x', 80),
        'social_facebook' => 'javascript:alert(1)', 'social_youtube' => 'https://youtube.com/uaz', 'social_tiktok' => 'mailto:a@b.c', 'header_color' => 'red',
    ]);

    expect($theme['header_logo'])->toBe('left') // emplacement inconnu : valeur d'origine
        ->and($theme['header_height'])->toBe(260)->and($theme['header_logo_size'])->toBe(32)
        ->and($theme['header_tag_text'])->not->toContain('<')->and(mb_strlen($theme['header_tag_text']))->toBeLessThanOrEqual(40)
        ->and($theme['social_facebook'])->toBe('')->and($theme['social_tiktok'])->toBe('')->and($theme['social_youtube'])->toBe('https://youtube.com/uaz')
        ->and($theme['header_color'])->toBe(NewsletterBlocks::defaultTheme()['header_color']);
});

it('reads an empty value as a real choice, not as a missing one (Laravel turns empty strings into null)', function () {
    $theme = NewsletterBlocks::theme(['header' => null, 'header_name' => null, 'header_rule' => null, 'header_tag' => null]);

    expect($theme['header'])->toBe('')->and($theme['header_name'])->toBe('')->and($theme['header_rule'])->toBe('')->and($theme['header_tag'])->toBe('')
        ->and($theme['header_logo'])->toBe('left'); // clé absente : valeur d'origine
});

it('saves a hidden header and hidden elements through the real request', function () {
    $letter = Newsletter::create(['subject' => 'Essai', 'blocks' => [], 'theme' => NewsletterBlocks::defaultTheme(), 'status' => Newsletter::DRAFT]);
    $theme = ['header' => '', 'header_name' => '', 'header_rule' => '', 'header_logo' => 'center'] + NewsletterBlocks::defaultTheme();

    $this->actingAs($this->editor)->putJson(route('admin.newsletter-editor.save', $letter), ['subject' => 'Essai', 'blocks' => [], 'theme' => $theme])->assertOk();

    expect($letter->fresh()->theme)->toMatchArray(['header' => '', 'header_name' => '', 'header_rule' => '', 'header_logo' => 'center']);
});

it('shows the editing marks of the header only on the artboard', function () {
    $theme = NewsletterBlocks::theme(NewsletterBlocks::modernHeader());

    expect(NewsletterRenderer::header($theme, true))->toContain('data-nl-header')->toContain('data-nl-theme-field="header_tag_text"')->toContain('data-nl-hdr');
    expect(NewsletterRenderer::header($theme))->not->toContain('data-nl');
    expect(NewsletterRenderer::html(['subject' => 'x', 'blocks' => [], 'theme' => $theme]))->not->toContain('data-nl-');
});

it('does not send an empty hashtag, but keeps its place on the artboard', function () {
    $theme = NewsletterBlocks::theme(['header_tag_text' => ''] + NewsletterBlocks::modernHeader());

    expect(NewsletterRenderer::header($theme, true))->toContain('data-nl-theme-field')
        ->and(NewsletterRenderer::header($theme))->not->toContain('font-weight:800');
});

it('stacks the elements of a zone that is too small instead of letting them overflow', function () {
    $html = NewsletterRenderer::header(NewsletterBlocks::theme(['header_tag' => 'right'] + NewsletterBlocks::modernHeader(['facebook' => 'https://a.b/c', 'instagram' => 'https://a.b/c', 'youtube' => 'https://a.b/c', 'linkedin' => 'https://a.b/c', 'tiktok' => 'https://a.b/c'])));

    expect($html)->toContain('padding-top:10px;'); // le hashtag et les icônes sont l'un sous l'autre
});

it('has the white and dark icon of every social network, and the large seal', function () {
    foreach (array_keys(NewsletterBlocks::SOCIALS) as $network) {
        expect(public_path("images/newsletter/{$network}-white.png"))->toBeFile()
            ->and(public_path("images/newsletter/{$network}-dark.png"))->toBeFile();
    }
    expect(public_path('images/uaz-seal-384.png'))->toBeFile();
});

it('gives every template the UAZ header, with the site social links', function () {
    SiteSetting::current()->update(['social_links' => ['facebook' => 'https://facebook.com/uazsite']]);

    foreach (NewsletterTemplates::keys() as $key) {
        $theme = NewsletterTemplates::build($key)['theme'];
        expect($theme)->toMatchArray(['header_logo' => 'center', 'header_tag' => 'left', 'header_social' => 'right', 'header_name' => '', 'social_facebook' => 'https://facebook.com/uazsite']);
    }
});

it('draws the header alone on request, for editors only', function () {
    $letter = Newsletter::create(['subject' => 'Essai', 'blocks' => [], 'theme' => NewsletterBlocks::defaultTheme(), 'status' => Newsletter::DRAFT]);
    $payload = ['theme' => NewsletterBlocks::modernHeader(['facebook' => 'https://facebook.com/uaz'])];

    $this->postJson(route('admin.newsletter-editor.header', $letter), $payload)->assertUnauthorized();
    $this->actingAs(User::factory()->create())->postJson(route('admin.newsletter-editor.header', $letter), $payload)->assertForbidden();

    $html = $this->actingAs($this->editor)->postJson(route('admin.newsletter-editor.header', $letter), $payload)->assertOk()->json('html');
    expect($html)->toContain('data-nl-header')->toContain('facebook-white.png')->toContain('#UAZINFOS');
});

it('tells the editor the header options and the site social links', function () {
    SiteSetting::current()->update(['social_links' => ['youtube' => 'https://youtube.com/uazsite']]);
    $letter = Newsletter::create(['subject' => 'Essai', 'blocks' => [], 'theme' => NewsletterBlocks::defaultTheme(), 'status' => Newsletter::DRAFT]);

    $html = str_replace('\/', '/', $this->actingAs($this->editor)->get(route('admin.newsletter-editor.show', $letter))->assertOk()->getContent());

    expect($html)->toContain('https://youtube.com/uazsite')->toContain('Réseaux sociaux')->toContain('Masqué');
});
