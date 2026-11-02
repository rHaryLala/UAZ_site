<?php

use App\Enums\TeamCategory;
use App\Models\Page;
use App\Models\TeamMember;
use App\Support\VisualBlocks;
use Database\Seeders\ContentTranslationsSeeder;
use Database\Seeders\StructureSeeder;

beforeEach(function () {
    $this->seed(StructureSeeder::class);

    $this->director = TeamMember::create([
        'name' => 'ANDRIAMBAHOAKA Manda', 'category' => TeamCategory::Administration, 'is_active' => true, 'sort_order' => 9,
        'job_title' => 'Directeur du Centre de recherche / Responsable domaine Sciences Sociales',
    ]);
    $this->secretary = TeamMember::create([
        'name' => 'IRINA Ho Liantsoa', 'category' => TeamCategory::Administration, 'is_active' => true, 'sort_order' => 1,
        'job_title' => 'Secrétaire Centre de recherche / Agent du bureau Académique',
    ]);
    TeamMember::create(['name' => 'Autre Personne', 'category' => TeamCategory::Administration, 'is_active' => true, 'job_title' => 'Comptable']);
});

it('serves the research centre page with its immersive scenes', function () {
    $this->get('/centre-de-recherche')
        ->assertOk()
        ->assertSee('data-res-hero', false)
        ->assertSee('data-res-statement', false)
        ->assertSee('data-res-people', false)
        ->assertSee('data-res-domain', false)
        ->assertSee('data-res-master', false)
        ->assertSee('data-res-invite', false)
        ->assertSee('<h1 id="res-title" class="res-title" data-res-title>Centre de recherche</h1>', false);
});

it('takes the acronym from the page title instead of writing it in the view', function () {
    $this->get('/centre-de-recherche')->assertOk()->assertSee('data-res-ring', false)->assertSee('<span>ZRC</span>', false);

    Page::where('key', 'research')->firstOrFail()->update(['title' => 'Centre de recherche']);

    $this->get('/centre-de-recherche')->assertOk()->assertDontSee('data-res-ring', false)->assertDontSee('ZRC');
});

it('presents only the people whose role names the research centre, the director first', function () {
    $html = $this->get('/centre-de-recherche')->assertOk()->getContent();

    expect($html)->toContain('ANDRIAMBAHOAKA Manda')->toContain('IRINA Ho Liantsoa')->not->toContain('Autre Personne')
        ->and(strpos($html, 'ANDRIAMBAHOAKA Manda'))->toBeLessThan(strpos($html, 'IRINA Ho Liantsoa'))
        ->and(substr_count($html, 'data-res-person'))->toBe(2);
});

it('names the domain of the director and lists its fields of study, nothing more', function () {
    $this->get('/centre-de-recherche')->assertOk()
        ->assertSee('data-res-chain', false)
        ->assertSee('Le centre est dirigé par ANDRIAMBAHOAKA Manda, responsable du domaine Sciences sociales.')
        ->assertSee('Gestion')->assertSee('Droit');

    // Sans fonction qui nomme un domaine, la section disparaît : on n'affirme rien de plus que ce que dit la fiche.
    $this->director->update(['job_title' => 'Directeur du Centre de recherche']);
    $this->get('/centre-de-recherche')->assertOk()->assertDontSee('data-res-domain', false)->assertDontSee('Mentions du domaine');
});

it('hides the placeholder text kept for the team and shows a sober notice until real blocks exist', function () {
    $html = $this->get('/centre-de-recherche')->assertOk()->getContent();

    expect($html)->not->toContain('TODO-CONTENU')
        ->and($html)->toContain('La présentation détaillée du centre (missions, axes de recherche, publications) sera publiée ici.');

    Page::where('key', 'research')->firstOrFail()->update(['blocks' => [
        ['type' => 'rich_text', 'data' => ['heading' => 'Axes de recherche', 'content' => '<p>Axe fourni par l\'UAZ.</p>', 'width' => 'normal']],
        ['type' => 'rich_text', 'data' => ['heading' => null, 'content' => '<p>TODO-CONTENU : à compléter.</p>', 'width' => 'normal']],
    ]]);

    $this->get('/centre-de-recherche')->assertOk()
        ->assertSee('Axes de recherche')->assertSee('Axe fourni par l&#039;UAZ.', false)
        ->assertDontSee('TODO-CONTENU')->assertDontSee('sera publiée ici');
});

it('degrades without team or domain and still invites the visitor to write to the university', function () {
    TeamMember::query()->delete();

    $this->get('/centre-de-recherche')->assertOk()
        ->assertDontSee('data-res-people', false)->assertDontSee('data-res-domain', false)
        ->assertSee('data-res-hero', false)
        ->assertSee('/contact?sujet=Centre%20de%20recherche', false);
});

it('quotes the official presentation sentence and links the Master admission page', function () {
    $this->get('/centre-de-recherche')->assertOk()
        ->assertSee('chaque étudiant bénéficie d&#039;un parcours sur mesure.', false)
        ->assertSee('orientée vers la recherche ou le monde professionnel')
        ->assertSee(url('/admissions/master'), false);
});

it('is served in English and Malagasy, with prefixed links and a translated sentence', function () {
    $this->seed(ContentTranslationsSeeder::class);

    $this->get('/en/centre-de-recherche')->assertOk()
        ->assertSee('<html lang="en"', false)
        ->assertSee('Research centre')
        ->assertSee('The centre is directed by ANDRIAMBAHOAKA Manda, head of the Social sciences field.')
        ->assertSee('will be published here')
        ->assertSee('/en/equipe"', false);

    $this->get('/mg/centre-de-recherche')->assertOk()->assertSee('<html lang="mg"', false)->assertSee('Foibe fikarohana');
});

it('is a hand-built layout that the visual editor does not try to rebuild', function () {
    expect(VisualBlocks::isBespoke(Page::where('key', 'research')->firstOrFail()))->toBeTrue();
});
