<?php

namespace App\Http\Controllers;

use App\Models\MediaAsset;
use App\Models\PhotoAlbum;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
            'wall' => $this->wall($albums->getCollection()),
            'photosTotal' => (int) PhotoAlbum::visible()->withCount('photos')->get()->sum('photos_count'),
        ]);
    }

    /**
     * Les images du mur du héros : les couvertures des albums, complétées par leurs photos tant qu'il y en a moins de douze
     * (avec un ou deux albums seulement, le mur resterait vide). Toujours tirées des albums publiés, jamais inventées.
     *
     * @param  Collection<int, PhotoAlbum>  $albums
     * @return Collection<int, MediaAsset>
     */
    private function wall($albums)
    {
        $wall = $albums->pluck('cover')->filter(fn ($cover) => $cover && $cover->file())->values();
        $target = 12;

        if ($wall->count() < $target && $albums->isNotEmpty()) {
            $ids = DB::table('photo_album_media_asset')->whereIn('photo_album_id', $albums->pluck('id'))
                ->orderBy('photo_album_id')->orderBy('position')->pluck('media_asset_id')->unique()->diff($wall->pluck('id'))->values();

            $photos = MediaAsset::with('media')->whereIn('id', $ids->take($target * 3))->get()->keyBy('id');
            $extra = $ids->map(fn ($id) => $photos->get($id))->filter(fn ($photo) => $photo && $photo->isImage())->take($target - $wall->count());
            $wall = $wall->concat($extra)->values();
        }

        return $wall;
    }

    public function show(PhotoAlbum $album): View
    {
        $album->load(['cover.media', 'photos.media']);

        return view('albums.show', ['album' => $album]);
    }
}
