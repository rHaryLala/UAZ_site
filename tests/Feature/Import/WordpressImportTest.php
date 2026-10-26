<?php

use App\Enums\TeamCategory;
use App\Models\ImportRecord;
use App\Models\MediaAsset;
use App\Models\NewsPost;
use App\Models\Page;
use App\Models\Redirect;
use App\Models\TeamMember;
use App\Models\Testimonial;
use App\Models\User;
use App\Services\Wordpress\ImportReport;
use App\Services\Wordpress\WordpressClient;
use App\Services\Wordpress\WordpressImporter;
use App\Support\Html;
use Database\Factories\PlaceholderImage;
use Database\Seeders\StructureSeeder;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    // L'importeur est testé, pas GD : pas de conversions d'images (lentes) et de toutes petites images.
    config(['media-library.disk_name' => 'public', 'uaz.media.max_file_size_kb' => 10240, 'uaz.media.conversions' => []]);
    $this->seed(StructureSeeder::class);
});

function wpFixture(string $name): array
{
    return json_decode(file_get_contents(base_path("tests/Fixtures/wordpress/$name.json")), true);
}

function fakeWordpress(array $override = []): void
{
    // Nouvelle fabrique à chaque appel : sinon les premiers stubs enregistrés l'emporteraient sur les suivants.
    Http::swap(new Factory);

    $image = file_get_contents(PlaceholderImage::make(200, 120));
    $pdf = "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF";

    Http::fake([
        '*/robots.txt' => Http::response("User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n"),
        '*/wp-json/wp/v2/media*' => Http::response($override['media'] ?? wpFixture('media'), 200, ['X-WP-TotalPages' => '1']),
        '*/wp-json/wp/v2/pages*' => Http::response($override['pages'] ?? wpFixture('pages'), 200, ['X-WP-TotalPages' => '1']),
        '*/wp-json/wp/v2/posts*' => Http::response($override['posts'] ?? wpFixture('posts'), 200, ['X-WP-TotalPages' => '1']),
        '*/wp-json/wp/v2/users*' => Http::response(wpFixture('users'), 200, ['X-WP-TotalPages' => '1']),
        '*/wp-content/uploads/*.pdf' => Http::response($pdf, 200, ['Content-Type' => 'application/pdf']),
        '*/wp-content/uploads/*' => Http::response($image, 200, ['Content-Type' => 'image/jpeg']),
    ]);
}

function runImport(bool $dry = false, array $only = [], bool $force = false): ImportReport
{
    $client = new WordpressClient('https://zurcher.edu.mg', 0, 'test', false);
    $report = new ImportReport($dry, $client->baseUrl());

    return (new WordpressImporter($client, $report, $dry, $force, base_path('docs/URL-MAP.csv')))->run($only);
}

it('writes nothing and downloads nothing in dry-run mode', function () {
    fakeWordpress();
    $before = [NewsPost::count(), MediaAsset::count(), TeamMember::count(), User::count()];

    $report = runImport(dry: true);

    expect([NewsPost::count(), MediaAsset::count(), TeamMember::count(), User::count()])->toBe($before)
        ->and(ImportRecord::count())->toBe(0)
        ->and($report->counts['post']['created'])->toBe(3)
        ->and($report->toMarkdown())->toContain('simulation');

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/wp-content/uploads/'));
});

it('only ever sends GET requests (the live site is read-only)', function () {
    fakeWordpress();
    runImport();

    Http::assertSent(fn ($request) => $request->method() === 'GET');
    Http::assertNotSent(fn ($request) => $request->method() !== 'GET');
});

it('imports articles with original dates, cleaned bodies, covers and regenerated slugs', function () {
    fakeWordpress();
    runImport();

    expect(NewsPost::count())->toBe(3);

    $junk = NewsPost::where('title', 'like', 'Janvier 2026%')->firstOrFail();
    $wp = collect(wpFixture('posts'))->firstWhere('slug', '1557-2');

    expect($junk->slug)->not->toBe('1557-2')->and($junk->slug)->toStartWith('janvier-2026')
        ->and($junk->published_at->utc()->toDateTimeString())->toBe(str_replace('T', ' ', $wp['date_gmt']))
        ->and($junk->isLive())->toBeTrue()
        ->and($junk->category->name)->toBe('Actualités')
        ->and($junk->body)->not->toContain('style=')->and($junk->body)->not->toContain('data-start')
        ->and($junk->excerpt)->not->toBeEmpty();

    foreach (NewsPost::all() as $post) {
        expect($post->cover)->not->toBeNull()->and($post->cover->alt)->not->toBeEmpty();
    }
});

it('decodes HTML entities in titles', function () {
    fakeWordpress();
    runImport();

    expect(NewsPost::where('title', 'Culte d’ouverture de l’année académique 2026-2027 : une nouvelle page s’ouvre à l’UAZ')->exists())->toBeTrue();
});

it('fills the system pages from WordPress and links their images to the media library', function () {
    fakeWordpress();
    runImport();

    $about = Page::where('key', 'about')->firstOrFail();
    $licence = Page::where('key', 'admissions-licence')->firstOrFail();

    expect(json_encode($about->blocks))->not->toContain('TODO-CONTENU')
        ->and($about->blocks[0]['data']['content'])->toContain('200 hectares')
        ->and($about->blocks[0]['data']['content'])->not->toContain('et_pb')
        ->and($licence->blocks[0]['data']['content'])->toContain('data-media=');

    $this->get('/a-propos')->assertOk()->assertSee('200 hectares');
    $this->get('/admissions/licence')->assertOk()->assertSee('<picture', false);
});

it('imports the team from the Personnel page with photos and categories', function () {
    fakeWordpress();
    runImport();

    expect(TeamMember::count())->toBe(3);

    $rector = TeamMember::where('name', 'like', '%KANCEL%')->firstOrFail();
    expect($rector->job_title)->toContain('Recteur')
        ->and($rector->email)->toBe('rector@zurcher.edu.mg')
        ->and($rector->photo)->not->toBeNull()->and($rector->photo->alt)->toContain('KANCEL')
        ->and($rector->category)->toBe(TeamCategory::Administration)
        ->and($rector->is_active)->toBeTrue();
});

it('imports the three YouTube testimonials disabled until an editor completes them', function () {
    fakeWordpress();
    $report = runImport();

    expect(Testimonial::count())->toBe(3)
        ->and(Testimonial::where('is_active', true)->count())->toBe(0)
        ->and(Testimonial::first()->video_url)->toStartWith('https://www.youtube.com/watch?v=')
        ->and(collect($report->review)->where('type', 'Témoignages'))->toHaveCount(3);
});

it('creates author accounts without any back-office role', function () {
    fakeWordpress();
    runImport();

    $editorial = User::where('name', 'Rédaction UAZ')->firstOrFail();

    expect($editorial->roles)->toHaveCount(0)->and($editorial->email)->toEndWith('@import.invalid')
        ->and(NewsPost::first()->author)->not->toBeNull();
});

it('skips oversized media and unsupported types and reports them', function () {
    fakeWordpress();
    $report = runImport(only: ['media']);

    $reasons = collect($report->review)->where('type', 'Médias');

    expect($reasons->contains(fn ($r) => str_contains($r['reason'], 'trop volumineux')))->toBeTrue()
        ->and($reasons->contains(fn ($r) => str_contains($r['reason'], 'type non autorisé')))->toBeTrue()
        ->and(MediaAsset::count())->toBeGreaterThan(5);
});

it('is idempotent: a second run creates nothing and duplicates nothing', function () {
    fakeWordpress();
    runImport();
    $counts = [NewsPost::count(), MediaAsset::count(), TeamMember::count(), Testimonial::count(), User::count(), Redirect::count(), ImportRecord::count()];

    $second = runImport();

    expect([NewsPost::count(), MediaAsset::count(), TeamMember::count(), Testimonial::count(), User::count(), Redirect::count(), ImportRecord::count()])->toBe($counts)
        ->and($second->counts['post']['created'] ?? 0)->toBe(0)
        ->and($second->counts['post']['skipped'])->toBe(3)
        ->and($second->counts['team']['created'] ?? 0)->toBe(0);
});

it('updates a post when WordPress changed it, but never overwrites local edits without --force', function () {
    fakeWordpress();
    runImport();

    $posts = wpFixture('posts');
    $posts[1]['content']['rendered'] .= '<p>Paragraphe ajouté sur WordPress.</p>';
    $posts[1]['modified'] = '2099-01-01T00:00:00';
    $target = NewsPost::where('title', 'like', 'Culte d%')->firstOrFail();

    // 1. Modification distante, rien d'édité localement : mise à jour.
    fakeWordpress(['posts' => $posts]);
    runImport();
    expect($target->fresh()->body)->toContain('Paragraphe ajouté sur WordPress.');

    // 2. Un éditeur retouche l'article ; WordPress le modifie encore : on ne l'écrase pas.
    $this->travel(5)->minutes();
    $target->update(['title' => 'Titre corrigé par la rédaction']);
    $posts[1]['content']['rendered'] .= '<p>Deuxième ajout distant.</p>';
    fakeWordpress(['posts' => $posts]);
    $report = runImport();

    expect($target->fresh()->title)->toBe('Titre corrigé par la rédaction')
        ->and($target->fresh()->body)->not->toContain('Deuxième ajout distant.')
        ->and(collect($report->review)->where('type', 'Articles'))->toHaveCount(1);

    // 3. --force remplace.
    runImport(force: true);
    expect($target->fresh()->body)->toContain('Deuxième ajout distant.');
});

it('never overwrites a system page that was edited by hand', function () {
    fakeWordpress();
    $about = Page::where('key', 'about')->firstOrFail();
    $about->update(['blocks' => [['type' => 'rich_text', 'data' => ['content' => '<p>Texte rédigé à la main.</p>']]]]);

    $report = runImport();

    expect(json_encode($about->fresh()->blocks, JSON_UNESCAPED_UNICODE))->toContain('Texte rédigé à la main.')
        ->and(collect($report->review)->where('type', 'Pages')->pluck('reason')->implode(' '))->toContain('modifiée dans le back-office');
});

it('generates 301 redirects from the URL map and from the real new addresses', function () {
    fakeWordpress();
    runImport();

    expect(Redirect::where('from_path', '/licence')->value('to_path'))->toBe('/admissions/licence');

    $wpSlug = '1557-2';
    $redirect = Redirect::where('from_path', '/'.$wpSlug)->first();
    $junk = NewsPost::where('title', 'like', 'Janvier 2026%')->firstOrFail();

    expect($redirect)->not->toBeNull()->and($redirect->to_path)->toBe('/actualites/'.$junk->slug)->and($redirect->status_code)->toBe(301);

    $this->get('/'.$wpSlug.'/')->assertStatus(301)->assertRedirect('/actualites/'.$junk->slug);
});

it('reports skipped legacy pages with the reason', function () {
    fakeWordpress();
    $report = runImport();

    $skipped = collect($report->notices)->where('type', 'Pages non importées')->pluck('ref')->implode(',');

    expect($skipped)->toContain('/blog')->and($skipped)->toContain('/accueil-2');
});

it('renders imported figures as responsive pictures through the sanitizer', function () {
    $asset = MediaAsset::factory()->withImage(1200, 800)->create(['alt' => 'Vue du campus']);

    $html = (string) Html::render('<p>Avant</p><figure data-media="'.$asset->id.'"><figcaption>Légende</figcaption></figure><p>Après</p>');

    expect($html)->toContain('<picture')->and($html)->toContain('alt="Vue du campus"')->and($html)->toContain('Légende')
        ->and($html)->toContain('width=')->and($html)->not->toContain('data-media');
});

it('drops figures whose media was deleted instead of breaking the page', function () {
    expect((string) Html::render('<p>Texte</p><figure data-media="999999"></figure>'))->toBe('<p>Texte</p>');
});

it('refuses paths disallowed by robots.txt', function () {
    Http::fake(['*/robots.txt' => Http::response("User-agent: *\nDisallow: /wp-json/\n")]);

    (new WordpressClient('https://zurcher.edu.mg', 0, 'test', false))->all('posts');
})->throws(RuntimeException::class, 'robots.txt');

it('runs from the command line, supports --only and validates its options', function () {
    fakeWordpress();

    $this->artisan('import:wordpress', ['--dry-run' => true, '--only' => 'posts'])
        ->expectsOutputToContain('Simulation')
        ->assertSuccessful();

    $this->artisan('import:wordpress', ['--dry-run' => true, '--only' => 'inconnu'])->assertExitCode(2);

    expect(NewsPost::count())->toBe(0);
});
