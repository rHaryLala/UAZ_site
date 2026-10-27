<?php

namespace App\Services\Wordpress;

use App\Enums\PublicationStatus;
use App\Enums\TeamCategory;
use App\Models\ImportRecord;
use App\Models\MediaAsset;
use App\Models\NewsCategory;
use App\Models\NewsPost;
use App\Models\Page;
use App\Models\Redirect;
use App\Models\TeamMember;
use App\Models\Testimonial;
use App\Models\User;
use App\Support\RedirectCsvImporter;
use Carbon\Carbon;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Importe le contenu de zurcher.edu.mg (API REST WordPress, lecture seule) : auteurs, médias référencés, pages,
 * articles, équipe, témoignages, puis redirections 301. Idempotent (table import_records) ; ne réécrit jamais un
 * contenu modifié depuis dans le back-office, sauf avec --force.
 */
class WordpressImporter
{
    public const STEPS = ['users', 'pages', 'posts', 'team', 'testimonials', 'redirects'];

    /** Page WordPress (slug) → clé de la page système du nouveau site. */
    private const PAGE_MAP = [
        'a-propos' => 'about',
        'admission' => 'admissions',
        'licence' => 'admissions-licence',
        'master' => 'admissions-master',
        'frais-academiques' => 'admissions-fees',
        'telechargements' => 'admissions-downloads',
        'vie-etudiante' => 'student-life',
        'bibliotheque' => 'student-life-library',
        'cafeteria' => 'student-life-cafeteria',
        'dortoirs' => 'student-life-dorms',
        'programmes' => 'lmd',
        'emplois-du-temps' => 'timetables',
    ];

    /** Pages volontairement non importées (voir docs/AUDIT.md §7) et motif. */
    private const SKIPPED_PAGES = [
        'home' => 'page vide dupliquée',
        'accueil' => "l'accueil est recomposé (réglages du site)",
        'accueil-2' => "doublon de l'accueil",
        's-a' => 'simple lien Facebook',
        'aumonerie' => 'cours en ligne daté (févr. 2026)',
        'cours-a-la-carte' => 'cours en ligne daté (févr. 2026)',
        'blog' => 'remplacé par la page Actualités',
        'academique' => 'remplacé par la liste des formations',
        'contactez-nous' => 'remplacé par le formulaire de contact et les réglages du site',
    ];

    /** @var array<string, string> chemin ancien (normalisé) → chemin nouveau */
    private array $pathMap = [];

    /** @var array<int, User> */
    private array $authors = [];

    private HtmlCleaner $cleaner;

    private MediaImporter $media;

    public function __construct(
        private readonly WordpressClient $client,
        private readonly ImportReport $report,
        private readonly bool $dryRun = false,
        private readonly bool $force = false,
        private readonly string $urlMapPath = '',
    ) {
        $this->cleaner = new HtmlCleaner(parse_url($client->baseUrl(), PHP_URL_HOST) ?: 'zurcher.edu.mg');
        $this->media = new MediaImporter($client, $report, $dryRun);
    }

    /** @param  list<string>  $only  sous-ensemble de self::STEPS ou « media » (tous les médias) ; vide = tout */
    public function run(array $only = []): ImportReport
    {
        $only = $only ?: self::STEPS;
        $want = fn (string $step) => in_array($step, $only, true);

        $pages = $posts = [];

        $this->media->index($this->client->all('media'));
        $this->loadPathMap();

        if ($want('users') || $want('posts')) {
            $this->users($this->client->all('users'));
        }
        if ($want('pages') || $want('team') || $want('testimonials')) {
            $pages = $this->client->all('pages', ['status' => 'publish']);
        }
        // Les articles sont aussi lus pour les pages : leurs liens internes pointent vers les nouvelles adresses.
        if ($want('posts') || $want('pages')) {
            $posts = $this->client->all('posts', ['status' => 'publish']);
            $this->registerPostPaths($posts);
        }

        if ($want('pages')) {
            $this->pages($pages);
        }
        if ($want('posts')) {
            $this->posts($posts);
        }
        if ($want('team')) {
            $this->team($pages);
        }
        if ($want('testimonials')) {
            $this->testimonials($pages);
        }
        if (in_array('media', $only, true) && ! $this->dryRun) {
            $this->allMedia();
        }
        if ($want('redirects')) {
            $this->redirects();
        }

        return $this->report;
    }

    public function media(): MediaImporter
    {
        return $this->media;
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Auteurs
    // ---------------------------------------------------------------------------------------------------------------

    /** @param  list<array<string, mixed>>  $users */
    private function users(array $users): void
    {
        foreach ($users as $wp) {
            $name = ($wp['slug'] ?? '') === 'uaz' ? 'Rédaction UAZ' : trim(html_entity_decode($wp['name'], ENT_QUOTES | ENT_HTML5));
            $record = ImportRecord::lookup('user', $wp['id']);

            if ($record && ($user = User::find($record->model_id))) {
                $this->authors[$wp['id']] = $user;
                $this->report->count('user', 'skipped');

                continue;
            }

            if ($this->dryRun) {
                $this->report->count('user', 'created');

                continue;
            }

            // Compte sans rôle (aucun accès au back-office) : sert uniquement à signer les articles.
            $user = User::firstOrCreate(
                ['email' => Str::slug($wp['slug'] ?: $name).'@import.invalid'],
                ['name' => $name, 'password' => Str::random(40)],
            );
            ImportRecord::remember('user', $wp['id'], $user, null, $wp['link'] ?? null);
            $this->authors[$wp['id']] = $user;
            $this->report->count('user', 'created');
        }
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Pages
    // ---------------------------------------------------------------------------------------------------------------

    /** @param  list<array<string, mixed>>  $pages */
    private function pages(array $pages): void
    {
        foreach ($pages as $wp) {
            $slug = $wp['slug'];
            $ref = '/'.trim(parse_url($wp['link'], PHP_URL_PATH) ?: $slug, '/');

            if (isset(self::SKIPPED_PAGES[$slug])) {
                $this->report->count('page', 'skipped');
                $this->report->notice('Pages non importées', $ref, self::SKIPPED_PAGES[$slug]);

                continue;
            }

            if (in_array($slug, ['personnel', 'temoignages'], true)) {
                continue; // traités par l'importeur d'équipe / de témoignages
            }

            $key = self::PAGE_MAP[$slug] ?? null;
            $page = $key ? Page::where('key', $key)->first() : null;

            if (! $page) {
                $this->report->count('page', 'skipped');
                $this->report->notice('Pages non importées', $ref, 'aucune page correspondante dans le nouveau site (à créer dans le back-office si utile)');

                continue;
            }

            $hash = md5(($wp['modified'] ?? '').$wp['content']['rendered']);
            $record = ImportRecord::lookup('page', $wp['id']);

            if ($record && ! $this->force && $record->hash === $hash) {
                $this->report->count('page', 'skipped');

                continue;
            }

            if (! $this->mayWrite($page, $record, $this->isPlaceholder($page))) {
                $this->report->count('page', 'skipped');
                $this->report->review('Pages', $ref, "la page « {$page->title} » a été modifiée dans le back-office : non écrasée (relancer avec --force pour la remplacer)");

                continue;
            }

            $html = $this->cleanContent($wp['content']['rendered'], $ref);

            if (blank(strip_tags($html))) {
                $this->report->count('page', 'skipped');
                $this->report->review('Pages', $ref, 'contenu vide après nettoyage (page à rédiger à la main)');

                continue;
            }

            $this->report->count('page', $record ? 'updated' : 'created');

            if (! $this->dryRun) {
                $page->update(['blocks' => [['type' => 'rich_text', 'data' => ['heading' => null, 'content' => $html, 'width' => 'wide']]]]);
                ImportRecord::remember('page', $wp['id'], $page, $hash, $wp['link']);
            }
        }
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Articles
    // ---------------------------------------------------------------------------------------------------------------

    /** @param  list<array<string, mixed>>  $posts */
    private function registerPostPaths(array $posts): void
    {
        foreach ($posts as $wp) {
            $this->pathMap[Redirect::normalize(parse_url($wp['link'], PHP_URL_PATH) ?: '/'.$wp['slug'])] = '/actualites/'.$this->postSlug($wp);
        }
    }

    /** @param  list<array<string, mixed>>  $posts */
    private function posts(array $posts): void
    {
        $category = $this->dryRun ? null : NewsCategory::firstOrCreate(['name' => 'Actualités']);

        foreach ($posts as $wp) {
            $title = $this->text($wp['title']['rendered']);
            $hash = md5(($wp['modified'] ?? '').$wp['content']['rendered']);
            $record = ImportRecord::lookup('post', $wp['id']);
            $post = $record ? NewsPost::find($record->model_id) : null;

            if ($post && ! $this->force && $record->hash === $hash) {
                $this->report->count('post', 'skipped');

                continue;
            }

            if ($post && ! $this->mayWrite($post, $record, false)) {
                $this->report->count('post', 'skipped');
                $this->report->review('Articles', $title, "modifié dans le back-office depuis l'import : non écrasé (--force pour le remplacer)");

                continue;
            }

            try {
                $body = $this->cleanContent($wp['content']['rendered'], $title);
                $excerpt = $this->excerpt($wp['excerpt']['rendered'] ?? '', $body);
                $cover = $this->cover($wp, $body, $title);

                $this->report->count('post', $post ? 'updated' : 'created');

                if ($this->dryRun) {
                    continue;
                }

                $attributes = [
                    'title' => $title,
                    'excerpt' => $excerpt,
                    'body' => $body,
                    'cover_id' => $cover?->id,
                    'author_id' => ($this->authors[$wp['author']] ?? null)?->id,
                    'news_category_id' => $category?->id,
                    'status' => ($wp['status'] ?? 'publish') === 'publish' ? PublicationStatus::Published : PublicationStatus::Draft,
                    'published_at' => Carbon::parse($wp['date_gmt'], 'UTC')->setTimezone(config('app.timezone')),
                ];

                DB::transaction(function () use (&$post, $attributes, $wp, $hash) {
                    if ($post) {
                        $post->update($attributes);
                    } else {
                        $post = new NewsPost($attributes + ['slug' => $this->postSlug($wp)]);
                        $post->save();
                    }
                    ImportRecord::remember('post', $wp['id'], $post, $hash, $wp['link']);
                });

                if (blank(strip_tags($body))) {
                    $this->report->review('Articles', $title, 'corps vide après nettoyage');
                }
            } catch (Throwable $e) {
                $this->report->fail('post', $title, $e->getMessage());
            }
        }
    }

    /** Slug conservé, sauf les slugs défectueux (« 1557-2 », « https-zurcher-edu-mg-blog ») régénérés depuis le titre. */
    private function postSlug(array $wp): string
    {
        $slug = $wp['slug'];

        if (preg_match('/^\d+(-\d+)?$/', $slug) || str_starts_with($slug, 'https-')) {
            return Str::limit(Str::slug($this->text($wp['title']['rendered'])), 80, '');
        }

        return $slug;
    }

    private function cover(array $wp, string $body, string $title): ?MediaAsset
    {
        $asset = ! empty($wp['featured_media']) ? $this->media->import((int) $wp['featured_media']) : null;

        // Sans image à la une : première image du contenu.
        if (! $asset && preg_match('/data-media="(\d+)"/', $body, $m)) {
            $asset = MediaAsset::find((int) $m[1]);
        }

        // Texte alternatif absent partout sur l'ancien site : on met le titre de l'article, à affiner par la rédaction.
        if ($asset && blank($asset->alt) && ! $this->dryRun) {
            $asset->update(['alt' => 'Illustration : '.Str::limit($title, 250, '')]);
            $this->report->review('Textes alternatifs', "média #{$asset->id}", 'texte alternatif provisoire (titre de l\'article) : à rédiger');
        }

        return $asset;
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Équipe et témoignages
    // ---------------------------------------------------------------------------------------------------------------

    /** @param  list<array<string, mixed>>  $pages */
    private function team(array $pages): void
    {
        $wp = collect($pages)->firstWhere('slug', 'personnel');

        if (! $wp) {
            return;
        }

        $dom = $this->dom($wp['content']['rendered']);
        $xpath = new DOMXPath($dom);
        $order = 0;

        foreach ($xpath->query("//div[contains(concat(' ', normalize-space(@class), ' '), ' et_pb_team_member ')]") as $node) {
            $name = trim(preg_replace('/\s+/u', ' ', $xpath->evaluate('string(.//h4)', $node)));
            $position = trim(preg_replace('/\s+/u', ' ', $xpath->evaluate("string(.//p[contains(@class,'et_pb_member_position')])", $node)));
            $order++;

            if ($name === '') {
                continue;
            }

            $key = Str::slug($name).'-'.$order;
            $record = ImportRecord::lookup('team', $key);

            if ($record && TeamMember::find($record->model_id)) {
                $this->report->count('team', 'skipped');

                continue;
            }

            $email = $this->firstEmail($node, $xpath);
            $img = $xpath->evaluate('string(.//img/@src)', $node);
            $category = preg_match('/enseignant|professeur|chargé|charge de cours|doyen|chef de d|maître|maitre|lecturer|enseignante|formateur/iu', $position)
                ? TeamCategory::Teaching : TeamCategory::Administration;

            $this->report->count('team', 'created');

            if ($this->dryRun) {
                continue;
            }

            $photo = $img ? $this->media->importByUrl($img) : null;

            if ($photo && blank($photo->alt)) {
                $photo->update(['alt' => 'Portrait de '.$name]);
            }

            $member = TeamMember::create([
                'name' => $name,
                'job_title' => $position ?: null,
                'category' => $category,
                'email' => $email,
                'photo_id' => $photo?->id,
                'sort_order' => $order,
                'is_active' => true,
            ]);
            ImportRecord::remember('team', $key, $member, null, $wp['link']);
        }

        $this->report->review('Équipe', 'Page Personnel', "{$order} personnes importées telles que publiées (noms, fonctions, e-mails) : confirmer qu'elles peuvent rester publiques ; catégorie (administration / enseignants) déduite de la fonction, à vérifier");
    }

    /** @param  list<array<string, mixed>>  $pages */
    private function testimonials(array $pages): void
    {
        $wp = collect($pages)->firstWhere('slug', 'temoignages');

        if (! $wp) {
            return;
        }

        preg_match_all('~youtube(?:-nocookie)?\.com/embed/([\w-]{6,})~', $wp['content']['rendered'], $m);

        foreach (array_values(array_unique($m[1])) as $i => $videoId) {
            $record = ImportRecord::lookup('testimonial', $videoId);

            if ($record && Testimonial::find($record->model_id)) {
                $this->report->count('testimonial', 'skipped');

                continue;
            }

            $this->report->count('testimonial', 'created');

            if (! $this->dryRun) {
                $testimonial = Testimonial::create([
                    'author_name' => 'Témoignage vidéo '.($i + 1).' (TODO-CONTENU)',
                    'video_url' => 'https://www.youtube.com/watch?v='.$videoId,
                    'sort_order' => $i,
                    'is_active' => false, // inactif tant que l'auteur et la citation ne sont pas saisis
                ]);
                ImportRecord::remember('testimonial', $videoId, $testimonial, null, $wp['link']);
            }

            $this->report->review('Témoignages', "vidéo YouTube $videoId", 'importée désactivée : renseigner le nom, la qualité et la citation, puis l\'activer');
        }
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Médias (import complet) et redirections
    // ---------------------------------------------------------------------------------------------------------------

    private function allMedia(): void
    {
        foreach ($this->media->ids() as $id) {
            $this->media->import($id);
        }
    }

    private function redirects(): void
    {
        if ($this->dryRun) {
            $this->report->notice('Redirections', 'URL-MAP.csv', 'importées lors d\'un import réel');

            return;
        }

        if ($this->urlMapPath !== '' && is_file($this->urlMapPath)) {
            $stats = app(RedirectCsvImporter::class)->import($this->urlMapPath);
            foreach (['created', 'updated', 'skipped'] as $what) {
                for ($i = 0; $i < $stats[$what]; $i++) {
                    $this->report->count('redirect', $what);
                }
            }
        }

        // Les articles et pages réellement importés font foi (slugs régénérés, etc.) : leurs redirections priment.
        foreach (['post' => NewsPost::class, 'page' => Page::class] as $source => $class) {
            ImportRecord::where('source', $source)->get()->each(function (ImportRecord $record) use ($class) {
                $model = $class::find($record->model_id);
                $from = Redirect::normalize(parse_url((string) $record->source_url, PHP_URL_PATH) ?: '/');

                if (! $model || $from === '/' || $from === Redirect::normalize(parse_url($model->url(), PHP_URL_PATH))) {
                    return;
                }

                Redirect::updateOrCreate(
                    ['from_path' => $from],
                    ['to_path' => parse_url($model->url(), PHP_URL_PATH) ?: '/', 'status_code' => 301, 'is_active' => true, 'note' => 'Import WordPress'],
                );
            });
        }
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Outils
    // ---------------------------------------------------------------------------------------------------------------

    private function loadPathMap(): void
    {
        if ($this->urlMapPath === '' || ! is_file($this->urlMapPath)) {
            return;
        }

        $handle = fopen($this->urlMapPath, 'r');
        $header = fgetcsv($handle, escape: '\\');

        while (($row = fgetcsv($handle, escape: '\\')) !== false) {
            $data = array_combine($header, array_pad($row, count($header), ''));

            if (! str_contains($data['ancienne_url'], '{')) {
                $this->pathMap[Redirect::normalize($data['ancienne_url'])] = $data['nouvelle_url'];
            }
        }

        fclose($handle);
    }

    private function cleanContent(string $html, string $ref): string
    {
        $clean = $this->cleaner->clean(
            $html,
            fn (string $url) => $this->media->importByUrl($url)?->id,
            fn (string $url) => $this->resolveLink($url),
        );

        foreach ($this->cleaner->unresolvedLinks() as $link) {
            $this->report->review('Liens internes non résolus', $ref, $link);
        }
        if (! $this->dryRun) {
            foreach ($this->cleaner->unresolvedImages() as $image) {
                $this->report->review('Images non importées', $ref, $image);
            }
        }
        foreach ($this->cleaner->warnings() as $warning) {
            $this->report->review('Contenu à vérifier', $ref, $warning);
        }

        return $clean;
    }

    private function resolveLink(string $url): ?string
    {
        $path = Redirect::normalize(parse_url($url, PHP_URL_PATH) ?: '/');

        if (str_starts_with($path, '/wp-content/uploads/')) {
            // Simulation : rien n'est téléchargé ; un fichier connu de la médiathèque WordPress compte comme résolu.
            if ($this->dryRun) {
                return $this->media->idForUrl($url) ? $path : null;
            }

            $asset = $this->media->importByUrl($url);

            return $asset?->file() ? (parse_url($asset->file()->getUrl(), PHP_URL_PATH) ?: null) : null;
        }

        return $this->pathMap[$path] ?? ($path === '/' ? '/' : null);
    }

    /** Autorise l'écriture si le contenu est un gabarit TODO, déjà synchronisé et non modifié, ou avec --force. */
    private function mayWrite(object $model, ?ImportRecord $record, bool $placeholder): bool
    {
        if ($this->force) {
            return true;
        }

        return $record ? ! $record->editedLocally($model) : $placeholder;
    }

    private function isPlaceholder(Page $page): bool
    {
        return str_contains(json_encode($page->blocks ?? []), 'TODO-CONTENU');
    }

    private function excerpt(string $wpExcerpt, string $body): ?string
    {
        $text = $this->text($wpExcerpt);
        $text = trim(preg_replace('/\s*(\[…\]|…|&hellip;)\s*$/u', '', $text));

        if ($text === '') {
            $text = $this->text(preg_match('#<p>(.*?)</p>#s', $body, $m) ? $m[1] : '');
        }

        return $text !== '' ? Str::limit($text, 240, '…') : null;
    }

    private function text(string $html): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    private function firstEmail(\DOMNode $node, DOMXPath $xpath): ?string
    {
        $mailto = $xpath->evaluate('string(.//a[starts-with(@href,"mailto:")]/@href)', $node);

        if ($mailto !== '' && preg_match('/mailto:([^?]+)/', $mailto, $m) && filter_var(trim($m[1]), FILTER_VALIDATE_EMAIL)) {
            return strtolower(trim($m[1]));
        }

        return preg_match('/[\w.+-]+@[\w-]+\.[\w.-]+/', $node->textContent, $m) ? strtolower($m[0]) : null;
    }

    private function dom(string $html): DOMDocument
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        return $dom;
    }
}
