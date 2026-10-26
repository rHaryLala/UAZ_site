<?php

use App\Services\Wordpress\HtmlCleaner;
use App\Support\Html;

function clean(string $html, ?callable $images = null, ?callable $links = null): array
{
    $cleaner = new HtmlCleaner('zurcher.edu.mg');

    return [$cleaner->clean($html, $images, $links), $cleaner];
}

it('removes Gutenberg comments, Divi shortcodes and builder wrappers but keeps the text', function () {
    [$html] = clean('<!-- wp:paragraph --><div class="et_pb_section"><div class="et_pb_row">[et_pb_text]<div class="et_pb_text_inner"><h2 class="et_pb_module_header">Notre campus</h2><p>Un campus de 200 hectares.</p></div>[/et_pb_text]</div></div><!-- /wp:paragraph -->');

    expect($html)->toBe('<h2>Notre campus</h2><p>Un campus de 200 hectares.</p>')
        ->and($html)->not->toContain('et_pb')->and($html)->not->toContain('[et_pb')->and($html)->not->toContain('<!--');
});

it('strips inline styles, classes, ids, editor artifacts and scripts', function () {
    [$html] = clean('<p style="text-align: justify;" class="x" data-start="194" data-end="581" id="a">Texte <span style="color:red">coloré</span><script>alert(1)</script></p><style>p{}</style><form><input name="x"></form>');

    expect($html)->toBe('<p>Texte coloré</p>');
});

it('turns h1 into h2 (the page title is the only h1) and b/i into strong/em', function () {
    [$html] = clean('<h1>Titre</h1><p><b>gras</b> et <i>italique</i></p>');

    expect($html)->toBe('<h2>Titre</h2><p><strong>gras</strong> et <em>italique</em></p>');
});

it('wraps loose text in paragraphs and drops empty ones', function () {
    [$html] = clean('<div>Texte libre<br>suite</div><p>&nbsp;</p><p></p><div><span> </span></div>');

    expect($html)->toBe('<p>Texte libre<br>suite</p>');
});

it('removes the bold artifact on long paragraphs but keeps short bold headings', function () {
    $long = str_repeat('Une très longue phrase éditoriale entièrement en gras. ', 4);
    [$html] = clean("<p><strong>$long</strong></p><p><strong>Titre court</strong></p>");

    expect($html)->not->toContain('<strong>'.substr($long, 0, 10))
        ->and($html)->toContain('<p><strong>Titre court</strong></p>');
});

it('rewrites internal links and reports the ones it cannot resolve', function () {
    $map = ['/licence' => '/admissions/licence'];
    [$html, $cleaner] = clean(
        '<p><a href="https://zurcher.edu.mg/licence/">Licence</a> <a href="https://zurcher.edu.mg/page-inconnue/">Inconnue</a> <a href="https://example.org/">Externe</a> <a href="mailto:a@b.mg">Mail</a> <a href="https://zurcher.edu.mg/licence/#docs">Ancre</a> <a href="javascript:alert(1)">XSS</a></p>',
        null,
        fn (string $url) => $map[rtrim(parse_url($url, PHP_URL_PATH), '/')] ?? null,
    );

    expect($html)->toContain('href="/admissions/licence"')
        ->and($html)->toContain('href="/admissions/licence#docs"')
        ->and($html)->toContain('href="https://example.org/"')
        ->and($html)->toContain('href="mailto:a@b.mg"')
        ->and($html)->not->toContain('javascript:')
        ->and($cleaner->unresolvedLinks())->toBe(['https://zurcher.edu.mg/page-inconnue/']);
});

it('replaces images with media references, drops the wrapping file link and keeps captions', function () {
    $resolver = fn (string $url) => str_contains($url, 'campus') ? 42 : null;
    [$html, $cleaner] = clean(
        '<p><a href="https://zurcher.edu.mg/wp-content/uploads/campus.jpg"><img src="https://zurcher.edu.mg/wp-content/uploads/campus-300x200.jpg" srcset="a 1x" class="wp-image-1" alt="x"></a></p>'
        .'<figure class="wp-block-image"><img src="https://zurcher.edu.mg/wp-content/uploads/campus.jpg"><figcaption>Vue aérienne</figcaption></figure>'
        .'<p><img src="https://zurcher.edu.mg/wp-content/uploads/inconnue.jpg" class="a" style="width:5px"></p>',
        $resolver,
    );

    expect($html)->toContain('<figure data-media="42"></figure>')
        ->and($html)->toContain('<figure data-media="42"><figcaption>Vue aérienne</figcaption></figure>')
        ->and($html)->toContain('<img src="https://zurcher.edu.mg/wp-content/uploads/inconnue.jpg">')
        ->and($html)->not->toContain('srcset')
        ->and($cleaner->unresolvedImages())->toBe(['https://zurcher.edu.mg/wp-content/uploads/inconnue.jpg']);
});

it('turns text-less image links to documents into accessible text links', function () {
    [$html] = clean(
        '<div><a href="https://zurcher.edu.mg/wp-content/uploads/2026/03/EDT-Informatique.pdf"><img src="https://zurcher.edu.mg/x/thumb.jpg" alt=""></a></div>'
        .'<p><a href="https://zurcher.edu.mg/wp-content/uploads/2026/03/Calendrier.pdf"><img src="https://zurcher.edu.mg/x/c.jpg" alt="Calendrier 2027"></a></p>',
    );

    expect($html)->toBe('<p><a href="https://zurcher.edu.mg/wp-content/uploads/2026/03/EDT-Informatique.pdf">Télécharger : EDT Informatique (PDF)</a></p>'
        .'<p><a href="https://zurcher.edu.mg/wp-content/uploads/2026/03/Calendrier.pdf">Télécharger : Calendrier 2027 (PDF)</a></p>');
});

it('replaces embedded YouTube videos with a link and drops other iframes', function () {
    [$html, $cleaner] = clean('<iframe src="https://www.youtube.com/embed/nLhy-kbE6T0?feature=oembed"></iframe><iframe src="https://tracker.example/x"></iframe>');

    expect($html)->toBe('<p><a href="https://www.youtube.com/watch?v=nLhy-kbE6T0">Voir la vidéo sur YouTube</a></p>')
        ->and($cleaner->warnings())->toHaveCount(2);
});

it('keeps tables and lists readable', function () {
    [$html] = clean('<div class="x"><table class="t" style="width:100%"><tbody><tr><td colspan="2" style="x">Frais</td></tr></tbody></table><ul class="l"><li><span>un</span></li><li>deux</li></ul></div>');

    expect($html)->toBe('<table><tbody><tr><td colspan="2">Frais</td></tr></tbody></table><ul><li>un</li><li>deux</li></ul>');
});

it('produces output that survives the sanitizer used at display time', function () {
    [$html] = clean('<div><p onclick="x()">Bonjour <a href="https://zurcher.edu.mg/licence/">lien</a></p></div>', null, fn () => '/admissions/licence');

    $shown = (string) Html::clean($html);

    expect($shown)->toContain('Bonjour')->and($shown)->toContain('/admissions/licence')->and($shown)->not->toContain('onclick');
});
