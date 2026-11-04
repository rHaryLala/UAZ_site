<?php

use App\Models\Menu;
use App\Models\Page;
use App\Support\VisualBlocks;
use Database\Seeders\ContentTranslationsSeeder;
use Database\Seeders\StructureSeeder;

beforeEach(fn () => $this->seed(StructureSeeder::class));

it('serves the page under Vie étudiante with its six scenes', function () {
    $html = $this->get('/vie-etudiante/association-des-etudiants')->assertOk()
        ->assertSee('data-sa-hero', false)->assertSee('data-sa-role', false)->assertSee('data-sa-elected', false)
        ->assertSee('data-sa-path', false)->assertSee('data-sa-eng', false)->assertSee('Association des étudiants')
        ->getContent();

    // « SA » : deux lettres géantes (décor), la chaîne VRAE → SA → activités, et trois étapes de candidature
    expect(substr_count($html, 'data-sa-letter>'))->toBe(2)
        ->and(substr_count($html, 'data-sa-node>'))->toBe(3)
        ->and(substr_count($html, 'data-sa-step>'))->toBe(3)
        ->and(substr_count($html, 'data-sa-principle>'))->toBe(3);
});

it('takes every fact from the Student Handbook, section 3.3', function () {
    $html = $this->get('/vie-etudiante/association-des-etudiants')->assertOk()->getContent();
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);
    $text = preg_replace('/\s+/u', ' ', str_replace("\u{00A0}", ' ', $text));

    expect($text)->toContain('coordonne toutes les activités prévues de l\'année')
        ->and($text)->toContain('sous la supervision du Vice-Recteur chargé des affaires estudiantines')
        ->and($text)->toContain('élus par tous les étudiants inscrits pendant l\'année académique')
        ->and($text)->toContain('équipe composée d\'au plus 15 noms')
        ->and($text)->toContain('les candidatures retenues font l\'objet d\'un entretien avec le VRAE')
        ->and($text)->toContain('promouvoir tous les projets et programmes émanant directement du VRAE')
        ->and($text)->toContain('Source : Manuel de l\'étudiant, section 3.3');
});

it('shows fifteen seats and invents no other figure', function () {
    $html = $this->get('/vie-etudiante/association-des-etudiants')->assertOk()->getContent();
    $main = substr($html, strpos($html, '<header class="sa-hero'), strpos($html, '<footer') - strpos($html, '<header class="sa-hero'));

    expect(substr_count($main, 'data-sa-seat'))->toBe(15)
        ->and($main)->toContain('data-final="15"');

    // Dans le texte de la page, seuls apparaissent : 15 (équipe), 3.3 et 3.4 (sections du manuel) et les numéros d'étapes 1, 2, 3.
    $text = html_entity_decode(strip_tags(preg_replace('/<(svg|script)\b.*?<\/\1>/s', ' ', $main)), ENT_QUOTES | ENT_HTML5);
    preg_match_all('/\d+(?:\.\d+)?/', $text, $numbers);
    expect(collect($numbers[0])->unique()->diff(['15', '3.3', '3.4', '1', '2', '3'])->all())->toBe([]);
});

it('keeps the handbook text in the page itself, for search and for editing', function () {
    $page = Page::where('key', 'student-life-association')->first();
    $content = collect($page->blocks)->pluck('data.content')->implode(' ');

    expect($content)->toContain('3.3 L’Association des étudiants')->toContain('d’au plus 15 noms')->toContain('estudiantines')
        ->and($page->path)->toBe('vie-etudiante/association-des-etudiants')
        ->and(VisualBlocks::isBespoke($page))->toBeTrue();
});

it('links to the handbook downloads and to the other student life pages', function () {
    $html = $this->get('/vie-etudiante/association-des-etudiants')->assertOk()->getContent();
    $end = substr($html, strpos($html, 'class="sa-end'));

    expect($end)->toContain(url('/admissions/telechargements'))
        ->and($end)->toContain(url('/vie-etudiante/bibliotheque'))->toContain(url('/vie-etudiante/cafeteria'))->toContain(url('/vie-etudiante/dortoirs'));
});

it('appears at the end of the Vie étudiante menu', function () {
    $tree = Menu::resolved('header');
    $children = collect(collect($tree)->firstWhere('label', 'Vie étudiante')['children']);

    expect($children->pluck('label')->all())->toBe(['Bibliothèque', 'Cafétéria', 'Dortoirs', 'Association des étudiants'])
        ->and($children->last()['url'])->toBe(url('/vie-etudiante/association-des-etudiants'));
});

it('is served in English and Malagasy with translated scenes', function () {
    $this->seed(ContentTranslationsSeeder::class);

    $this->get('/en/vie-etudiante/association-des-etudiants')->assertOk()->assertSee('<html lang="en"', false)
        ->assertSee('Student association')->assertSee('names at most')->assertSee('The path of a candidacy for president');
    $this->get('/mg/vie-etudiante/association-des-etudiants')->assertOk()->assertSee('<html lang="mg"', false)
        ->assertSee('Fikambanan&#039;ny mpianatra', false)->assertSee('anarana raha be indrindra');
});

it('is not shown while the page is a draft', function () {
    Page::where('key', 'student-life-association')->update(['status' => 'draft']);

    $this->get('/vie-etudiante/association-des-etudiants')->assertNotFound();
});
