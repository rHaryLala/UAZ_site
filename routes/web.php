<?php

use App\Http\Controllers\AlbumController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DesignSystemController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\FormationController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SearchPreviewController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\TeamController;
use App\Models\Event;
use App\Models\Formation;
use App\Models\NewsPost;
use App\Models\Page;
use App\Models\PhotoAlbum;
use Illuminate\Support\Facades\Route;

// Catalogue du design system : jamais exposé en production (config/uaz.php).
if (config('uaz.design_system_enabled')) {
    Route::get('/design-system', DesignSystemController::class)->name('design-system');
}

// Liaison de route publique : seuls les contenus visibles (publiés, ou tous en mode aperçu) répondent ;
// un brouillon ou un contenu programmé donne une 404.
Route::bind('formation', fn (string $slug) => Formation::visible()->where('slug', $slug)->firstOrFail());
Route::bind('post', fn (string $slug) => NewsPost::visible()->where('slug', $slug)->firstOrFail());
Route::bind('event', fn (string $slug) => Event::visible()->where('slug', $slug)->firstOrFail());
Route::bind('album', fn (string $slug) => PhotoAlbum::visible()->where('slug', $slug)->firstOrFail());

Route::get('/', HomeController::class)->name('home');

Route::get('/formations', [FormationController::class, 'index'])->name('formations.index');
Route::get('/formations/{formation}', [FormationController::class, 'show'])->name('formations.show');

Route::get('/actualites', [NewsController::class, 'index'])->name('news.index');
Route::get('/actualites/{post}', [NewsController::class, 'show'])->name('news.show');

Route::get('/evenements', [EventController::class, 'index'])->name('events.index');
Route::get('/evenements/{event}', [EventController::class, 'show'])->name('events.show');

Route::get('/galerie', [AlbumController::class, 'index'])->name('albums.index');
Route::get('/galerie/{album}', [AlbumController::class, 'show'])->name('albums.show');

Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');

Route::get('/meteo', \App\Http\Controllers\WeatherController::class)->middleware('throttle:30,1')->name('weather');
Route::get('/yearbook/{year?}', [\App\Http\Controllers\YearbookController::class, 'show'])->where('year', '[0-9]{4}')->name('yearbook');
Route::get('/soutenir/{slug}', [\App\Http\Controllers\ProjectController::class, 'show'])->name('projects.show');
Route::get('/equipe', TeamController::class)->name('team');
Route::get('/contact', ContactController::class)->name('contact');
Route::get('/recherche', SearchController::class)->name('search');
Route::get('/recherche/apercu', SearchPreviewController::class)->middleware('throttle:60,1')->name('search.preview');

// Pages libres (arbre de pages du back-office) : toujours en dernier. Les chemins techniques sont exclus.
Route::get('/{path}', PageController::class)
    ->where('path', '^(?!'.implode('|', array_map('preg_quote', Page::RESERVED_SLUGS)).')[a-z0-9\-/]+$')
    ->name('pages.show');
