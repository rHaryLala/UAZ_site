<?php

namespace App\Http\Controllers;

use App\Models\PhotoAlbum;
use Illuminate\Contracts\View\View;

class AlbumController extends Controller
{
    public function index(): View
    {
        return view('albums.index', [
            'albums' => PhotoAlbum::visible()->with('cover.media')->withCount('photos')
                ->orderByDesc('taken_on')->orderByDesc('published_at')->paginate(12),
        ]);
    }

    public function show(PhotoAlbum $album): View
    {
        $album->load(['cover.media', 'photos.media']);

        return view('albums.show', ['album' => $album]);
    }
}
