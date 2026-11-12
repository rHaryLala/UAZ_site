<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Models\StudioDesign;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Studio UAZ : l'éditeur de visuels (écrans de direct, annonces, versets, félicitations…) aux couleurs de l'université. Le dessin et l'export se font
 * dans le navigateur (public/js/admin/studio/) ; le serveur garde les visuels de chaque personne (brouillons enregistrés tout seuls) et sert la
 * médiathèque. Accès : comptes autorisés à ajouter des médias ; chacun ne voit et ne modifie que ses visuels.
 */
class StudioController extends Controller
{
    private const MAX_FIELD = 800;

    private const MAX_PHOTOS = 6;

    public function index(Request $request): View|RedirectResponse
    {
        if (! $request->user()) {
            return redirect('/admin/login');
        }
        $this->authorizeStudio($request);

        return view('admin.studio.index', ['boot' => [
            'designs' => $this->summaries($request),
            'urls' => [
                'designs' => route('admin.studio.designs.index'),
                'design' => url('/admin/studio/designs'),
                'media' => route('admin.media-browser.browse'),
                'upload' => route('admin.media-browser.upload'),
                'retouche' => route('admin.retouche.index'),
                'back' => url('/admin'),
                'seal' => asset('images/uaz-seal-384.png'),
                'themeLogo' => asset('images/studio/theme-clair.png'),
                'fonts' => ['latin' => asset('fonts/studio/inter-latin.woff2'), 'latinExt' => asset('fonts/studio/inter-latin-ext.woff2')],
            ],
            'formats' => StudioDesign::FORMATS,
        ]]);
    }

    public function list(Request $request): JsonResponse
    {
        $this->authorizeStudio($request);

        return response()->json(['designs' => $this->summaries($request)]);
    }

    public function show(Request $request, StudioDesign $design): JsonResponse
    {
        $this->authorizeDesign($request, $design);

        return response()->json(['design' => $this->describe($design)]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeStudio($request);

        $design = StudioDesign::create(['user_id' => $request->user()->id] + $this->validated($request));

        return response()->json(['design' => $this->describe($design)], 201);
    }

    public function update(Request $request, StudioDesign $design): JsonResponse
    {
        $this->authorizeDesign($request, $design);

        $design->update($this->validated($request));

        return response()->json(['design' => ['id' => $design->id, 'name' => $design->name, 'updated_at' => $design->updated_at?->toIso8601String()]]);
    }

    public function destroy(Request $request, StudioDesign $design): JsonResponse
    {
        $this->authorizeDesign($request, $design);

        $design->delete();

        return response()->json(['ok' => true]);
    }

    private function authorizeStudio(Request $request): void
    {
        abort_unless($request->user()?->can('create', MediaAsset::class), 403);
    }

    private function authorizeDesign(Request $request, StudioDesign $design): void
    {
        $this->authorizeStudio($request);
        abort_unless($design->user_id === $request->user()->id, 403);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'template' => ['required', 'string', 'regex:/^[a-z0-9-]{2,40}$/'],
            'format' => ['required', Rule::in(StudioDesign::FORMATS)],
            'data' => ['required', 'array'],
            'data.champs' => ['nullable', 'array', 'max:40'],
            'data.photos' => ['nullable', 'array', 'max:'.self::MAX_PHOTOS],
            'thumb' => ['nullable', 'string', 'max:90000', 'starts_with:data:image/jpeg;base64,'],
        ]);

        $fields = [];
        foreach ((array) ($data['data']['champs'] ?? []) as $key => $value) {
            if (is_string($key) && preg_match('/^[a-z0-9_]{1,30}$/i', $key) && (is_scalar($value) || $value === null)) {
                $fields[$key] = is_bool($value) ? $value : mb_substr(trim(strip_tags((string) $value)), 0, self::MAX_FIELD);
            }
        }

        $photos = [];
        foreach ((array) ($data['data']['photos'] ?? []) as $slot => $photo) {
            if (! is_string($slot) || ! preg_match('/^[a-z0-9_]{1,24}$/i', $slot) || ! is_array($photo) || (int) ($photo['media'] ?? 0) < 1) {
                continue;
            }
            $focus = array_values((array) ($photo['focus'] ?? [0.5, 0.4]));
            $photos[$slot] = [
                'media' => (int) $photo['media'],
                'focus' => [round(min(1, max(0, (float) ($focus[0] ?? 0.5))), 3), round(min(1, max(0, (float) ($focus[1] ?? 0.4))), 3)],
                'zoom' => round(min(3, max(1, (float) ($photo['zoom'] ?? 1))), 2),
            ];
        }

        return [
            'name' => trim(strip_tags($data['name'])) ?: 'Sans titre',
            'template' => $data['template'],
            'format' => $data['format'],
            'data' => ['champs' => $fields, 'photos' => $photos],
            'thumb' => $data['thumb'] ?? null,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function summaries(Request $request): array
    {
        return StudioDesign::query()->where('user_id', $request->user()->id)->latest('updated_at')->limit(60)
            ->get(['id', 'name', 'template', 'format', 'thumb', 'updated_at'])
            ->map(fn (StudioDesign $design) => [
                'id' => $design->id, 'name' => $design->name, 'template' => $design->template, 'format' => $design->format,
                'thumb' => $design->thumb, 'updated' => $design->updated_at?->translatedFormat('j F Y, H:i'),
            ])->all();
    }

    /**
     * Le visuel avec, pour chaque photo, son adresse actuelle dans la médiathèque (une photo supprimée depuis disparaît du visuel).
     *
     * @return array<string, mixed>
     */
    private function describe(StudioDesign $design): array
    {
        $photos = [];
        $assets = MediaAsset::with('media')->whereIn('id', collect($design->data['photos'] ?? [])->pluck('media')->all())->get()->keyBy('id');

        foreach ((array) ($design->data['photos'] ?? []) as $slot => $photo) {
            $asset = $assets->get($photo['media'] ?? 0);

            if ($asset && $asset->isImage()) {
                [$width, $height] = $asset->dimensions();
                $photos[$slot] = $photo + ['url' => $asset->url(''), 'width' => $width, 'height' => $height, 'alt' => (string) $asset->alt];
            }
        }

        return [
            'id' => $design->id, 'name' => $design->name, 'template' => $design->template, 'format' => $design->format,
            'data' => ['champs' => (array) ($design->data['champs'] ?? []), 'photos' => $photos],
        ];
    }
}
