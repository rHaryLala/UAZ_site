<?php

use App\Models\Page;
use App\Support\TimetableSheets;
use App\Support\VisualBlocks;
use Database\Seeders\ContentTranslationsSeeder;
use Database\Seeders\StructureSeeder;

beforeEach(function () {
    $this->seed(StructureSeeder::class);
    $this->page = Page::where('key', 'timetables')->first();
});

/** Les emplois du temps sont des liens PDF saisis dans le texte de la page, comme dans le back-office. */
function timetableLinks(Page $page, array $links, string $extra = ''): void
{
    $html = collect($links)->map(fn ($label, $href) => '<p><a href="'.$href.'">'.$label.'</a></p>')->implode('').$extra;
    $page->update(['blocks' => [['type' => 'rich_text', 'data' => ['heading' => null, 'content' => $html, 'width' => 'wide']]]]);
}

it('serves the timetables page with its immersive scenes', function () {
    timetableLinks($this->page, ['/storage/1/EDT-Mention-Droit.pdf' => 'Télécharger : EDT Mention Droit (PDF)']);

    $this->get('/emplois-du-temps')->assertOk()
        ->assertSee('data-tt-hero', false)->assertSee('data-tt-ticker', false)
        ->assertSee('data-tt-sheets', false)->assertSee('data-tt-invite', false)
        ->assertSee('Emplois du temps');
});

it('builds one sheet per PDF, named after the matching formation and its faculty', function () {
    timetableLinks($this->page, [
        '/storage/43/EDT-Mention-Droit.pdf' => 'Télécharger : EDT Mention Droit (PDF)',
        '/storage/44/EDT-Informatique.pdf' => 'Télécharger : EDT Informatique (PDF)',
        '/storage/47/EDT-Etudes-Anglophones.pdf' => 'Télécharger : EDT Etudes Anglophones (PDF)',
    ]);

    $html = $this->get('/emplois-du-temps')->assertOk()->getContent();

    expect(substr_count($html, 'data-tt-card'))->toBe(3)
        ->and($html)->toContain('Licence en Droit')->toContain('Sciences sociales')
        ->and($html)->toContain('Licence en Informatique')
        // « Etudes Anglophones » (sans accent dans le lien) retrouve la formation « Licence en Études anglophones ».
        ->and($html)->toContain('Licence en Études anglophones')->toContain('Arts &amp; lettres')
        ->and($html)->toContain('href="/storage/43/EDT-Mention-Droit.pdf"')->toContain('rel="noopener noreferrer"')
        ->and($html)->toContain('/formations/licence-en-droit');
});

it('keeps the cleaned label when no formation has that name, and invents no faculty', function () {
    timetableLinks($this->page, ['/storage/9/EDT-Doctorat.pdf' => 'Télécharger : EDT Doctorat (PDF)']);

    $sheets = TimetableSheets::from($this->page->fresh());

    expect($sheets)->toHaveCount(1)
        ->and($sheets[0]['name'])->toBe('Doctorat')
        ->and($sheets[0]['faculty'])->toBeNull()
        ->and($sheets[0]['formationUrl'])->toBeNull();
});

it('reads the size of the hosted file and shows none for an external or missing one', function () {
    $file = public_path('tt-test-'.uniqid().'.pdf');
    file_put_contents($file, str_repeat('x', 5000));

    try {
        timetableLinks($this->page, [
            '/'.basename($file) => 'EDT Droit (PDF)',
            '/storage/0/absent.pdf' => 'EDT Gestion (PDF)',
            'https://example.test/edt.pdf' => 'EDT Théologie (PDF)',
        ]);
        $sheets = TimetableSheets::from($this->page->fresh());
    } finally {
        @unlink($file);
    }

    expect($sheets->pluck('size')->all())->toBe([5, null, null]);
});

it('gives each mention the same drawn pattern every time, built from five columns', function () {
    timetableLinks($this->page, ['/storage/43/EDT-Mention-Droit.pdf' => 'EDT Mention Droit (PDF)']);

    $first = TimetableSheets::from($this->page->fresh())[0]['art'];
    $again = TimetableSheets::from($this->page->fresh())[0]['art'];

    expect($first)->toBe($again)->and($first)->not->toBeEmpty()
        ->and(collect($first)->every(fn ($b) => $b[0] >= 0 && $b[0] <= 4 && $b[1] + $b[2] <= 8 && $b[3] <= 2))->toBeTrue();
});

it('lists the same PDF once and keeps the text that is not a timetable link', function () {
    timetableLinks($this->page, [
        '/storage/43/EDT-Mention-Droit.pdf' => 'EDT Droit (PDF)',
    ], '<p>Les horaires peuvent changer en cours de semestre.</p>');
    $this->page->update(['blocks' => array_merge($this->page->fresh()->blocks, [['type' => 'rich_text', 'data' => ['heading' => null, 'content' => '<p><a href="/storage/43/EDT-Mention-Droit.pdf">Doublon</a></p>', 'width' => 'wide']]])]);

    $html = $this->get('/emplois-du-temps')->assertOk()->assertSee('Les horaires peuvent changer en cours de semestre.')->getContent();

    expect(substr_count($html, 'data-tt-card'))->toBe(1)
        ->and(TimetableSheets::remainder($this->page->fresh()))->toHaveCount(1);
});

it('says so plainly when there is no timetable to present', function () {
    $this->page->update(['blocks' => []]);

    $this->get('/emplois-du-temps')->assertOk()
        ->assertSee('Les emplois du temps arrivent bientôt')
        ->assertDontSee('data-tt-card', false)->assertDontSee('data-tt-ticker', false);
});

it('is a bespoke page, outside the visual editor', function () {
    expect(VisualBlocks::isBespoke($this->page))->toBeTrue();
});

it('is served in English and Malagasy with translated scenes', function () {
    $this->seed(ContentTranslationsSeeder::class);
    timetableLinks($this->page, ['/storage/43/EDT-Mention-Droit.pdf' => 'Télécharger : EDT Mention Droit (PDF)']);

    $this->get('/en/emplois-du-temps')->assertOk()
        ->assertSee('<html lang="en"', false)
        ->assertSee('Find the timetable of your programme')
        ->assertSee('Bachelor&#039;s in Law', false)->assertSee('Open the timetable');

    $this->get('/mg/emplois-du-temps')->assertOk()->assertSee('<html lang="mg"', false)->assertSee('Sokafy ny fandaharam-potoana');
});
