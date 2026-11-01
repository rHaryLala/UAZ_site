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

        // « Soutenir l'UAZ » : page dédiée aux dons et aux projets.
        if ($page->key === 'support') {
            return view('pages.support', [
                'page' => $page,
                'projects' => \App\Models\Project::active()->with('cover.media')->get(),
            ]);
        }

        // Bibliothèque Paul Pichot : mise en page immersive. Les deux images du texte importé (enseigne, affiche des horaires)
        // sont reprises dans l'ordre ; la salle de lecture est l'image de couverture de la page (champ « Image » du back-office).
        if ($page->key === 'student-life-library') {
            preg_match_all('/data-media="(\d+)"/', collect($page->blocks)->pluck('data.content')->implode(' '), $found);
            $media = \App\Models\MediaAsset::query()->with('media')->whereIn('id', $found[1])->get()->keyBy('id');

            return view('pages.library', [
                'page' => $page,
                'trail' => $trail,
                'hoursPoster' => $media->get((int) ($found[1][1] ?? 0)),
                'interior' => $page->cover ?? $media->get((int) ($found[1][0] ?? 0)),
            ]);
        }

        // Cafétéria : mise en page immersive. Les trois photos du texte importé sont reprises dans l'ordre (service au buffet, cuisine, équipe).
        if ($page->key === 'student-life-cafeteria') {
            preg_match_all('/data-media="(\d+)"/', collect($page->blocks)->pluck('data.content')->implode(' '), $found);
            $media = \App\Models\MediaAsset::query()->with('media')->whereIn('id', $found[1])->get()->keyBy('id');

            return view('pages.cafeteria', [
                'page' => $page,
                'trail' => $trail,
                'service' => $media->get((int) ($found[1][0] ?? 0)),
                'kitchen' => $media->get((int) ($found[1][1] ?? 0)),
                'team' => $media->get((int) ($found[1][2] ?? 0)),
            ]);
        }

        return view('pages.show', [
            'page' => $page,
            'trail' => $trail,
            'children' => $page->children()->visible()->with('cover.media')->get(),
        ]);
    }
}
