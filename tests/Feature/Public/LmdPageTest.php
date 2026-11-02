<?php

use App\Models\Formation;
use App\Models\Page;
use App\Support\VisualBlocks;
use Database\Seeders\ContentTranslationsSeeder;
use Database\Seeders\StructureSeeder;

beforeEach(fn () => $this->seed(StructureSeeder::class));

it('serves the LMD page with its five scenes and the three giant letters', function () {
    $html = $this->get('/systeme-lmd')->assertOk()
        ->assertSee('data-lmd-hero', false)->assertSee('data-lmd-climb', false)->assertSee('id="licence"', false)
        ->assertSee('id="master"', false)->assertSee('id="doctorat"', false)->assertSee('Système LMD')
        ->getContent();

    // L, M, D : initiales des trois grades, chacune renvoie à sa section
    preg_match_all('/data-lmd-letter="\d"/', $html, $letters);
    expect($letters[0])->toHaveCount(3)
        ->and($html)->toMatch('/lmd-letter__big" aria-hidden="true">L</')->toMatch('/lmd-letter__big" aria-hidden="true">M</')->toMatch('/lmd-letter__big" aria-hidden="true">D</')
        ->and($html)->toContain('href="#licence"')->toContain('href="#master"')->toContain('href="#doctorat"');
});

it('takes every figure from the published text: years, semesters, credits, and the Bac+ levels', function () {
    $html = $this->get('/systeme-lmd')->assertOk()->getContent();

    expect($html)->toContain('3 ans')->toContain('6 semestres')->toContain('180 crédits ECTS')->toContain('120 crédits ECTS')
        ->and($html)->toContain('300 crédits ECTS depuis le Bac')->toContain('Bac+3')->toContain('Bac+5')->toContain('M1 · M2')
        // la jauge : 180 / 300 = 60 % pour la Licence, 40 % pour le Master
        ->and($html)->toContain('stroke-dasharray: 60 100')->toContain('stroke-dasharray: 40 100; stroke-dashoffset: -60')
        ->and($html)->toContain('data-final="300"')->toContain('data-licence="180"');
});

it('gives the Doctorate no figure at all, only what the page says', function () {
    $html = $this->get('/systeme-lmd')->assertOk()->getContent();
    $doctorate = substr($html, strpos($html, 'id="doctorat"'));

    expect($doctorate)->toContain('poursuite vers un cycle de Doctorat')->not->toMatch('/ECTS|\d+ ans/');
    // et sa marche de l'escalier n'annonce ni durée ni crédits
    $step = substr($html, strpos($html, 'La suite</p>'), 600);
    expect($step)->not->toContain('ECTS');
});

it('lists the seven Licence mentions with their tracks, linking to the formation when it is published', function () {
    $html = $this->get('/systeme-lmd')->assertOk()->getContent();
    $licence = substr($html, strpos($html, 'id="licence"'), strpos($html, 'id="master"') - strpos($html, 'id="licence"'));

    expect(substr_count($licence, 'data-lmd-mention>'))->toBe(7)
        ->and($licence)->toContain('Système d&#039;information et génie logiciel')->toContain('Maïeutique (sage-femme)')->toContain('Média et Journalisme')
        ->and($licence)->toContain('/formations/licence-en-informatique')->toContain('/formations/licence-en-droit');

    Formation::where('mention', 'Droit')->update(['status' => 'draft']);
    $again = $this->get('/systeme-lmd')->assertOk()->getContent();
    expect($again)->not->toContain('/formations/licence-en-droit')->toMatch('/lmd-mention__name">\s*Droit\s*</'); // la mention reste, sans lien
});

it('presents the three Master mentions and the two stages M1 and M2', function () {
    $html = $this->get('/systeme-lmd')->assertOk()->getContent();
    $master = substr($html, strpos($html, 'id="master"'), strpos($html, 'id="doctorat"') - strpos($html, 'id="master"'));

    expect(substr_count($master, 'data-lmd-mention>'))->toBe(3)->and(substr_count($master, 'data-lmd-stage-item'))->toBe(2)
        ->and($master)->toContain('>M1<')->toContain('>M2<')->toContain('Administration des affaires')->toContain('LEAP')->toContain('LIAP');
});

it('is a page of its own, outside the visual editor', function () {
    expect(VisualBlocks::isBespoke(Page::where('key', 'lmd')->first()))->toBeTrue();
});

it('appears in the Ressources menu of the site and in the resources of the home page', function () {
    $home = $this->get('/')->assertOk()->getContent();

    expect($home)->toContain('href="'.url('/systeme-lmd').'"')
        ->and(substr_count($home, 'data-home-res-item'))->toBe(4);
});

it('is served in English and Malagasy with translated data', function () {
    $this->seed(ContentTranslationsSeeder::class);

    $this->get('/en/systeme-lmd')->assertOk()->assertSee('<html lang="en"', false)
        ->assertSee('Three degrees, one path')->assertSee('180 ECTS credits')->assertSee('Business administration')->assertSee('Our programmes');
    $this->get('/mg/systeme-lmd')->assertOk()->assertSee('<html lang="mg"', false)->assertSee('Ambaratonga telo, lalana iray')->assertSee('Fitantanana raharaha');
});
