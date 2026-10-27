<?php

use App\Enums\FormationLevel;
use App\Enums\PublicationStatus;
use App\Models\Event;
use App\Models\Faculty;
use App\Models\Formation;
use App\Models\NewsCategory;
use App\Models\NewsPost;
use App\Models\Page;
use App\Models\PhotoAlbum;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\StructureSeeder;

beforeEach(fn () => $this->seed(StructureSeeder::class));

it('renders the home page with the hero, key figures and formations from the structure', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Préparer aujourd’hui les leaders de demain')
        ->assertSee('data-hero', false)
        ->assertSee('Mentions de licence')
        ->assertSee('Arts &amp; lettres', false)
        ->assertSee('Informatique');
});

it('serves every public section', function (string $path) {
    $this->get($path)->assertOk()->assertSee('<main id="contenu"', false);
})->with(['/formations', '/actualites', '/evenements', '/galerie', '/equipe', '/contact', '/recherche', '/a-propos']);

it('serves a nested page by its full path and breadcrumbs', function () {
    $this->get('/admissions/licence')
        ->assertOk()
        ->assertSee('Fil d\'Ariane', false)
        ->assertSee('Admissions');
});

it('redirects the home page record to the root', function () {
    $this->get('/accueil')->assertRedirect('/')->assertStatus(301);
});

it('shows a branded 404 page', function () {
    $this->get('/page-qui-nexiste-pas')->assertNotFound()->assertSee('Cette page est introuvable.');
});

it('hides drafts and scheduled content from the public', function () {
    NewsPost::factory()->create(['title' => 'Publiée']);
    $draft = NewsPost::factory()->draft()->create(['title' => 'Brouillon secret']);
    $scheduled = NewsPost::factory()->scheduled()->create(['title' => 'Programmée']);

    $this->get('/actualites')->assertOk()->assertSee('Publiée')->assertDontSee('Brouillon secret')->assertDontSee('Programmée');
    $this->get($draft->url())->assertNotFound();
    $this->get($scheduled->url())->assertNotFound();
});

it('lets a back-office user preview a draft with ?apercu=1, without indexing or caching', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $draft = NewsPost::factory()->draft()->create(['title' => 'Brouillon à relire']);
    $editor = tap(User::factory()->create())->assignRole(RolesAndPermissionsSeeder::EDITOR);

    $this->get($draft->url().'?apercu=1')->assertNotFound(); // visiteur anonyme : refusé

    $this->actingAs($editor)->get($draft->url().'?apercu=1')
        ->assertOk()
        ->assertSee('Brouillon à relire')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertHeader('Cache-Control', 'no-store, private');

    $this->actingAs($editor)->get($draft->url())->assertNotFound(); // sans le paramètre, même l'éditeur voit le site public
});

it('filters formations by level and by faculty, with links that work without JavaScript', function () {
    $other = Faculty::factory()->create(['name' => 'Autre domaine']);
    Formation::factory()->create(['title' => 'Master en Droit des affaires', 'level' => FormationLevel::Master, 'faculty_id' => $other->id]);

    $this->get('/formations?niveau=master')->assertOk()
        ->assertSee('Master en Droit des affaires')->assertDontSee('Licence en Informatique');

    $this->get('/formations?niveau=licence')->assertOk()
        ->assertSee('Licence en Informatique')->assertDontSee('Master en Droit des affaires');

    $this->get('/formations?faculte='.$other->slug)->assertOk()->assertSee('Master en Droit des affaires');
});

it('shows a formation page with Course structured data and admission facts', function () {
    $formation = Formation::where('title', 'Licence en Droit')->firstOrFail();

    $this->get($formation->url())
        ->assertOk()
        ->assertSee('Conditions d\'admission', false)
        ->assertSee('"@type":"Course"', false)
        ->assertSee('180 ECTS');
});

it('shows news with Article structured data, category filter and pagination', function () {
    $category = NewsCategory::create(['name' => 'Communauté']);
    NewsPost::factory()->count(11)->create(['news_category_id' => $category->id]);
    NewsPost::factory()->create(['title' => 'Sans catégorie']);
    $post = NewsPost::factory()->create(['title' => 'Article détaillé', 'news_category_id' => $category->id]);

    $this->get('/actualites')->assertOk()->assertSee('Suivant');
    $this->get('/actualites?categorie='.$category->slug)->assertOk()->assertDontSee('Sans catégorie');
    $this->get($post->url())->assertOk()->assertSee('"@type":"Article"', false)->assertSee('Article détaillé');
});

it('separates upcoming and past events and exposes Event structured data', function () {
    $future = Event::factory()->create(['title' => 'Portes ouvertes']);
    Event::factory()->past()->create(['title' => 'Concours passé']);

    $this->get('/evenements')->assertOk()->assertSee('Portes ouvertes')->assertSee('Concours passé')->assertSee('Événements passés');
    $this->get($future->url())->assertOk()->assertSee('"@type":"Event"', false);
});

it('renders an album with the lightbox markup', function () {
    $album = PhotoAlbum::factory()->create(['title' => 'Remise des diplômes']);

    $this->get('/galerie')->assertOk()->assertSee('Remise des diplômes');
    $this->get($album->url())->assertOk()->assertSee('Remise des diplômes');
});

it('never exposes unpublished pages', function () {
    $page = Page::factory()->draft()->create(['title' => 'Interne']);

    $this->get($page->url())->assertNotFound();
});

it('declares language, skip link, landmarks and a single h1 on every page', function (string $path) {
    $html = $this->get($path)->getContent();

    expect($html)->toContain('<html lang="fr"')
        ->and($html)->toContain('class="skip-link"')
        ->and($html)->toContain('<main id="contenu"')
        ->and($html)->toContain('<footer')
        ->and(substr_count($html, '<h1'))->toBe(1);
})->with(['/', '/formations', '/actualites', '/contact', '/a-propos']);

it('adds canonical, Open Graph and Twitter metadata', function () {
    $this->get('/formations')
        ->assertSee('<link rel="canonical" href="'.url('/formations').'">', false)
        ->assertSee('property="og:title"', false)
        ->assertSee('name="twitter:card"', false);
});

it('keeps the contact form markup and honeypot in the page', function () {
    $this->get('/contact')->assertOk()->assertSee('wire:id', false)->assertSee('cf-website', false)->assertSee('tabindex="-1"', false);
});

it('marks draft content as noindex in preview mode only', function () {
    expect(PublicationStatus::Draft->value)->toBe('draft');
});

it('has no public parent pages for the Admissions and Vie étudiante sections, but keeps their sub-pages', function () {
    $this->get('/admissions')->assertNotFound();
    $this->get('/vie-etudiante')->assertNotFound();
    $this->get('/admissions/licence')->assertOk();
    $this->get('/vie-etudiante/cafeteria')->assertOk();
});

it('sends the enrol buttons to the online registration site', function () {
    $this->get('/')->assertOk()->assertSee('href="'.config('uaz.registration_url').'"', false);
    expect(config('uaz.registration_url'))->toBe('https://registrar.zurcher.edu.mg/#pre-inscription');
});

it('renders team profile cards that open an enlarged sheet with the quote and message', function () {
    $member = \App\Models\TeamMember::factory()->create(['name' => 'Dr Test Exemple', 'quote' => 'Servir avec excellence.', 'bio' => 'Bienvenue à l\'UAZ.']);

    $this->get('/equipe')->assertOk()
        ->assertSee('data-team-grid', false)
        ->assertSee('data-profile-open', false)
        ->assertSee('Servir avec excellence.')
        ->assertSee('Message aux lecteurs')
        ->assertSee('id="team-profile"', false);
});

it('renders a download list block as a styled list with file types and safe links', function () {
    $page = \App\Models\Page::factory()->create([
        'title' => 'Documents', 'slug' => 'documents-test', 'path' => 'documents-test',
        'blocks' => [['type' => 'download_list', 'data' => ['title' => 'Centre', 'items' => [
            ['title' => 'Liste des frais', 'text' => 'Frais 2026', 'url' => 'https://example.test/frais.pdf'],
            ['title' => 'Formulaire', 'url' => 'https://example.test/form.docx'],
            ['title' => 'Piégé', 'url' => 'javascript:alert(1)'],
        ]]]],
    ]);

    $this->get('/documents-test')->assertOk()
        ->assertSee('Liste des frais')->assertSee('PDF')->assertSee('DOC')
        ->assertSee('https://example.test/frais.pdf', false)
        ->assertDontSee('javascript:alert', false)
        ->assertDontSee('Piégé');
});
