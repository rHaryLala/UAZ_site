<?php

use App\Http\Controllers\Admin\MediaBrowserController;
use App\Http\Controllers\Admin\NewsletterEditorController;
use App\Http\Controllers\Admin\RetouchePhotoController;
use App\Http\Controllers\Admin\StudioController;
use App\Http\Controllers\Admin\VisualEditorController;
use App\Http\Controllers\AlbumController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DesignSystemController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\FormationController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SearchPreviewController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\WeatherController;
use App\Http\Controllers\YearbookController;
use App\Http\Middleware\SetLocale;
use App\Models\Event;
use App\Models\Formation;
use App\Models\Page;
use App\Support\Locales;
use Illuminate\Support\Facades\Route;

// Catalogue du design system : jamais exposé en production (config/uaz.php).
if (config('uaz.design_system_enabled')) {
    Route::get('/design-system', DesignSystemController::class)->name('design-system');
}

// Les liaisons {formation}, {post}, {event} et {album} sont dans AppServiceProvider : ce fichier n'est pas exécuté quand les routes sont en cache.

// Hors des langues : fichiers techniques et outils du back-office.
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');
// Sélecteur de médias du back-office (parcourir et envoyer) : réservé aux comptes autorisés (vérifié dans le contrôleur).
Route::prefix('admin/media-browser')->name('admin.media-browser.')->group(function () {
    Route::get('/', [MediaBrowserController::class, 'browse'])->name('browse');
    Route::post('/upload', [MediaBrowserController::class, 'upload'])->middleware('throttle:60,1')->name('upload');
});
// Éditeur visuel des pages (droits vérifiés dans le contrôleur : politique « update » de la page).
Route::prefix('admin/visual-editor/{page}')->name('admin.visual-editor.')->group(function () {
    $controller = VisualEditorController::class;
    Route::get('/', [$controller, 'show'])->name('show');
    Route::get('/canvas', [$controller, 'canvas'])->name('canvas');
    Route::get('/preview', [$controller, 'preview'])->name('preview');
    Route::post('/render', [$controller, 'render'])->middleware('throttle:240,1')->name('render');
    Route::put('/draft', [$controller, 'saveDraft'])->middleware('throttle:120,1')->name('draft');
    Route::delete('/draft', [$controller, 'discardDraft'])->name('draft.discard');
    Route::post('/publish', [$controller, 'publish'])->middleware('throttle:30,1')->name('publish');
});

// Retouche photo : l'atelier plein écran, et la recherche / le remplacement de la photo d'une personne de l'équipe (droits vérifiés dans le contrôleur).
Route::prefix('admin/retouche-photo')->name('admin.retouche.')->group(function () {
    Route::get('/', [RetouchePhotoController::class, 'index'])->name('index');
    Route::match(['get', 'post'], '/personnes', [RetouchePhotoController::class, 'people'])->middleware('throttle:60,1')->name('people');
});

// Studio UAZ : l'éditeur de visuels plein écran et les visuels enregistrés de chaque personne (droits vérifiés dans le contrôleur).
Route::prefix('admin/studio')->name('admin.studio.')->group(function () {
    Route::get('/', [StudioController::class, 'index'])->name('index');
    Route::get('/designs', [StudioController::class, 'list'])->name('designs.index');
    Route::post('/designs', [StudioController::class, 'store'])->middleware('throttle:60,1')->name('designs.store');
    Route::get('/designs/{design}', [StudioController::class, 'show'])->name('designs.show');
    Route::put('/designs/{design}', [StudioController::class, 'update'])->middleware('throttle:120,1')->name('designs.update');
    Route::delete('/designs/{design}', [StudioController::class, 'destroy'])->name('designs.destroy');
});

// Éditeur visuel des newsletters (droits vérifiés dans le contrôleur : politique NewsletterPolicy).
Route::prefix('admin/newsletter-editor/{newsletter}')->name('admin.newsletter-editor.')->group(function () {
    $controller = NewsletterEditorController::class;
    Route::get('/', [$controller, 'show'])->name('show');
    Route::post('/render', [$controller, 'render'])->middleware('throttle:newsletter-render')->name('render');
    Route::post('/rows', [$controller, 'rows'])->middleware('throttle:newsletter-render')->name('rows');
    Route::post('/header', [$controller, 'header'])->middleware('throttle:newsletter-render')->name('header');
    Route::get('/templates', [$controller, 'templates'])->middleware('throttle:newsletter-render')->name('templates');
    Route::put('/save', [$controller, 'save'])->middleware('throttle:newsletter-save')->name('save');
    Route::post('/test', [$controller, 'test'])->middleware('throttle:newsletter-test')->name('test');
    Route::post('/send', [$controller, 'send'])->middleware('throttle:newsletter-send')->name('send');
});

/*
 * Site public, enregistré une fois par langue : français sans préfixe, anglais sous /en, malagasy sous /mg
 * (config/uaz.php). Les noms de route des langues préfixées commencent par « en. » / « mg. » ; les liens du site
 * sont toujours générés avec les noms français (route('news.index'), url('/formations')) : App\Support\Locales::apply()
 * ajoute le préfixe de la langue courante à la racine des URL.
 */
$publicRoutes = function (): void {
    Route::get('/', HomeController::class)->name('home');

    Route::get('/formations', [FormationController::class, 'index'])->name('formations.index');
    Route::get('/formations/{formation}', [FormationController::class, 'show'])->name('formations.show');

    Route::get('/actualites', [NewsController::class, 'index'])->name('news.index');
    Route::get('/actualites/{post}', [NewsController::class, 'show'])->name('news.show');

    Route::get('/evenements', [EventController::class, 'index'])->name('events.index');
    Route::get('/evenements/{event}', [EventController::class, 'show'])->name('events.show');
    Route::get('/evenements/{event}/agenda.ics', [EventController::class, 'calendar'])->name('events.ics');

    Route::get('/galerie', [AlbumController::class, 'index'])->name('albums.index');
    Route::get('/galerie/{album}', [AlbumController::class, 'show'])->name('albums.show');

    Route::get('/meteo', WeatherController::class)->middleware('throttle:30,1')->name('weather');
    Route::get('/yearbook/{year?}', [YearbookController::class, 'show'])->where('year', '[0-9]{4}')->name('yearbook');
    Route::get('/soutenir/{slug}', [ProjectController::class, 'show'])->name('projects.show');
    Route::get('/equipe', TeamController::class)->name('team');
    Route::get('/contact', ContactController::class)->name('contact');

    // Newsletter : abonnement immédiat (JSON, depuis la fenêtre du site) et désinscription par lien signé.
    Route::post('/newsletter', [NewsletterController::class, 'subscribe'])->middleware('throttle:6,1')->name('newsletter.subscribe');
    Route::get('/newsletter/desinscription/{subscriber}', [NewsletterController::class, 'unsubscribe'])->middleware('signed')->name('newsletter.unsubscribe');
    // Envoi d'essai : le lien du pied de page mène ici (il fonctionne, mais ne désinscrit personne).
    Route::get('/newsletter/desinscription-essai', [NewsletterController::class, 'unsubscribeTest'])->middleware('signed')->name('newsletter.unsubscribe.test');
    Route::get('/recherche', SearchController::class)->name('search');
    Route::get('/recherche/apercu', SearchPreviewController::class)->middleware('throttle:60,1')->name('search.preview');

    // Pages libres (arbre de pages du back-office) : toujours en dernier. Les chemins techniques sont exclus.
    Route::get('/{path}', PageController::class)
        ->where('path', '^(?!(?:'.implode('|', array_map('preg_quote', Page::RESERVED_SLUGS)).')(?:/|$))[a-z0-9\-/]+$')
        ->name('pages.show');
};

foreach (Locales::prefixed() as $code) {
    Route::prefix($code)->name($code.'.')->middleware(SetLocale::class.':'.$code)->group($publicRoutes);
}
// Le français passe en dernier : sa route « pages libres » attrape tout ce qui n'est pas réservé.
Route::middleware(SetLocale::class.':'.Locales::default())->group($publicRoutes);
