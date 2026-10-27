<?php

namespace App\Http\Controllers\Admin;

use App\Filament\Forms\MediaField;
use App\Filament\Support\MediaPicker;
use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Données du sélecteur de médias du back-office : parcourir la médiathèque (recherche, type, pages) et y envoyer des fichiers. */
class MediaBrowserController extends Controller
{
    private const PER_PAGE = 36;

    public function browse(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('viewAny', MediaAsset::class), 403);

        $term = trim((string) $request->query('q', ''));
        $type = (string) $request->query('type', 'all');

        $query = MediaAsset::query()->with('media')
            ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('title', 'like', "%$term%")
                ->orWhere('alt', 'like', "%$term%")
                ->orWhereHas('media', fn ($m) => $m->where('file_name', 'like', "%$term%"))))
            ->when($type === 'image', fn ($q) => $q->whereHas('media', fn ($m) => $m->where('mime_type', 'like', 'image/%')))
            ->when($type === 'pdf', fn ($q) => $q->whereHas('media', fn ($m) => $m->where('mime_type', 'application/pdf')))
            ->latest('id');

        $page = $query->paginate(self::PER_PAGE, page: max(1, (int) $request->query('page', 1)));

        return response()->json([
            'data' => $page->getCollection()->map(fn (MediaAsset $asset) => MediaField::describe($asset))->values(),
            'has_more' => $page->hasMorePages(),
        ]);
    }

    public function upload(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('create', MediaAsset::class), 403);

        $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:30'],
            'files.*' => ['file', 'mimetypes:'.implode(',', config('uaz.media.accepted_mime_types')), 'max:'.config('uaz.media.max_file_size_kb')],
            'alts' => ['required', 'array'],
            'alts.*' => ['required', 'string', 'min:3', 'max:300'],
        ], [
            'files.*.mimetypes' => 'Format non accepté : JPEG, PNG, WebP ou PDF seulement.',
            'files.*.max' => 'Fichier trop lourd (limite : '.round(config('uaz.media.max_file_size_kb') / 1024).' Mo).',
            'alts.*.required' => 'Le texte alternatif est obligatoire pour chaque fichier.',
            'alts.*.min' => 'Le texte alternatif est trop court : décrivez ce que montre le fichier.',
        ]);

        $created = collect($request->file('files'))->map(function ($file, $index) use ($request) {
            $path = $file->getRealPath();
            $title = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

            $asset = MediaAsset::create(['title' => $title, 'alt' => (string) $request->input("alts.$index")]);
            $asset->addMedia($path)->usingFileName($file->getClientOriginalName())->toMediaCollection(MediaAsset::COLLECTION);

            return MediaField::describe($asset->refresh()->load('media'));
        });

        return response()->json(['data' => $created->values()], 201);
    }
}
