<?php

use App\Models\MediaAsset;
use App\Models\Page;
use Database\Seeders\StructureSeeder;
use Illuminate\Support\Carbon;

beforeEach(fn () => $this->seed(StructureSeeder::class));
afterEach(fn () => Carbon::setTestNow());

it('serves the licence and master pages with their own identity and scenes', function () {
    foreach (['licence' => 'Licence', 'master' => 'Master'] as $slug => $title) {
        $this->get("/admissions/$slug")
            ->assertOk()
            ->assertSee('data-adm-hero', false)
            ->assertSee('data-adm-calendar', false)
            ->assertSee('data-adm-dossier', false)
            ->assertSee('data-adm-mentions', false)
            ->assertSee('data-adm-contact', false)
            ->assertSee("adm--$slug", false)
            ->assertSee('Admission 2026-2027')
            ->assertSee('registrar@zurcher.edu.mg');
    }
});

it('lists the nine documents and the mentions of the official posters and texts', function () {
    $licence = $this->get('/admissions/licence')->assertOk()->getContent();
    $master = $this->get('/admissions/master')->assertOk()->getContent();

    expect(substr_count($licence, 'data-adm-doc>'))->toBe(9)
        ->and(substr_count($licence, 'data-adm-card>'))->toBe(8)
        ->and($licence)->toContain('Une chemise cartonnée jaune')->toContain('Pasteur de district')->toContain('Maïeutique (sage-femme)')
        ->toContain('note minimale de 10/20 en mathématiques pour le A2')
        ->and(substr_count($master, 'data-adm-doc>'))->toBe(9)
        ->and(substr_count($master, 'data-adm-card>'))->toBe(3)
        ->and($master)->toContain('17 000')->toContain('Mobile Money')->toContain('19 h – 21 h')->toContain('Linguistique appliquée');
});

it('marks each date as past, next or upcoming according to the day in Madagascar', function () {
    Page::where('key', 'admissions-licence')->update(['published_at' => '2020-01-01']);
    Carbon::setTestNow(Carbon::parse('2026-10-07 12:00', 'Indian/Antananarivo'));
    $html = $this->get('/admissions/licence')->assertOk()->getContent();

    // 5 octobre passé, 9 octobre = prochaine étape, 15 mars 2027 à venir.
    expect($html)->toMatch('/data-state="past".*?5<\/span>/s')
        ->and(substr_count($html, 'data-state="past"'))->toBe(1)
        ->and(substr_count($html, 'data-state="next"'))->toBe(1)
        ->and(substr_count($html, 'data-state="future"'))->toBe(1)
        ->and($html)->toContain('data-adm-count="2"');

    Carbon::setTestNow(Carbon::parse('2027-06-01', 'Indian/Antananarivo'));
    $later = $this->get('/admissions/licence')->assertOk()->getContent();

    expect(substr_count($later, 'data-state="past"'))->toBe(3)->and($later)->not->toContain('data-adm-count');
});

it('reads the downloadable forms from the imported text and tolerates a missing poster', function () {
    Page::where('key', 'admissions-licence')->firstOrFail()->update([
        'blocks' => [['type' => 'rich_text', 'data' => ['content' => '<p><a href="https://example.org/a.docx">x</a> <a href="https://example.org/b.doc">y</a> <a href="https://example.org/page">z</a></p>']]],
    ]);

    $html = $this->get('/admissions/licence')->assertOk()->getContent();

    expect(substr_count($html, 'adm-forms__link'))->toBe(2)
        ->and($html)->toContain('https://example.org/a.docx')->toContain('Formulaire d&#039;admission')->toContain('Lettre de recommandation')
        ->not->toContain('https://example.org/page')
        ->not->toContain('class="adm-poster"');
});

it('shows the poster and the cover photo when the media exist, and links the licence mentions to their formation', function () {
    $poster = MediaAsset::create(['title' => 'Affiche', 'alt' => '']);
    Page::where('key', 'admissions-licence')->firstOrFail()->update([
        'blocks' => [['type' => 'rich_text', 'data' => ['content' => '<figure data-media="'.$poster->id.'"></figure>']]],
    ]);

    $html = $this->get('/admissions/licence')->assertOk()->getContent();

    // Les médias de test n'ont pas de fichier : la page reste servie ; les mentions renvoient vers leur fiche.
    expect($html)->toContain('/formations/licence-en-droit')->toContain('/formations/licence-en-informatique');
});

it('does not use the admissions layout for the other pages of the section', function () {
    $this->get('/admissions/frais-academiques')->assertOk()->assertDontSee('data-admissions', false);
});
