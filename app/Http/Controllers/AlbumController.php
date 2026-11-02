<?php

namespace App\Http\Controllers;

use App\Models\PhotoAlbum;
use Illuminate\Contracts\View\View;

class AlbumController extends Controller
{
    public function index(): View
    {
        $albums = PhotoAlbum::visible()->with('cover.media')->withCount('photos')
            ->orderByDesc('taken_on')->orderByDesc('published_at')->paginate(24);

        // Les années servent au filtre : il n'a de sens que si tous les albums tiennent sur une seule page.
        $years = $albums->hasPages() ? collect() : $albums->getCollection()->map(fn ($album) => $album->taken_on?->year)->filter()->unique()->sortDesc()->values();

        return view('albums.index', [
            'albums' => $albums,
            'years' => $years,
            'photosTotal' => (int) PhotoAlbum::visible()->withCount('photos')->get()->sum('photos_count'),
        ]);
    }

    public function show(PhotoAlbum $album): View
    {
        $album->load(['cover.media', 'photos.media']);

        return view('albums.show', ['album' => $album]);
    }
}
