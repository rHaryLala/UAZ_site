<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/** Pages libres du back-office, servies par leur chemin complet (ex. admissions/licence). */
class PageController extends Controller
{
    public function __invoke(string $path): View|RedirectResponse
    {
        $page = Page::query()->visible()->where('path', trim($path, '/'))->with(['cover.media', 'ogImage.media', 'parent'])->firstOrFail();

        if ($page->key === 'home') {
            return redirect('/', 301);
        }

        // Fil d'Ariane : ancêtres puis page courante.
        $trail = [];
        for ($parent = $page->parent; $parent; $parent = $parent->parent) {
            array_unshift($trail, ['label' => $parent->title, 'url' => $parent->url()]);
        }
        $trail[] = ['label' => $page->title];

        // La page « À propos » a sa propre mise en page éditoriale (vidéo, histoire, campus…).
        if ($page->key === 'about') {
            return view('pages.about', [
                'page' => $page,
                'events' => \App\Models\Event::visible()->upcoming()->with('cover.media')->orderBy('starts_at')->limit(3)->get(),
            ]);
        }

        return view('pages.show', [
            'page' => $page,
            'trail' => $trail,
            'children' => $page->children()->visible()->with('cover.media')->get(),
        ]);
    }
}
