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

    private const MAX_PHOTOS = 14;

    private const MAX_LIBRES = 40;

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
            'data.options' => ['nullable', 'array'],
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
            'data' => ['champs' => $fields, 'photos' => $photos, 'options' => $this->options((array) ($data['data']['options'] ?? []))],
            'thumb' => $data['thumb'] ?? null,
        ];
    }

    /**
     * Ce que la personne a changé sur le visuel : éléments masqués, déplacés ou agrandis, et éléments ajoutés à la main. Tout est borné et nettoyé.
     *
     * @param  array<string, mixed>  $raw
     * @return array{masques: list<string>, decalages: array<string, array<string, array{0: int, 1: int}>>, tailles: array<string, float>, libres: list<array<string, mixed>>}
     */
    private function options(array $raw): array
    {
        $id = fn (mixed $v): bool => is_string($v) && (bool) preg_match('/^[a-z0-9_:-]{1,40}$/i', $v);
        $num = fn (mixed $v, float $min, float $max, float $default = 0): float => round(min($max, max($min, is_numeric($v) ? (float) $v : $default)), 4);
        $color = fn (mixed $v, string $default): string => is_string($v) && preg_match('/^#[0-9a-f]{6}$/i', $v) ? strtolower($v) : $default;

        $masques = array_values(array_unique(array_filter((array) ($raw['masques'] ?? []), $id)));

        $decalages = [];
        foreach ((array) ($raw['decalages'] ?? []) as $format => $items) {
            if (! in_array($format, StudioDesign::FORMATS, true) || ! is_array($items)) {
                continue;
            }
            foreach ($items as $key => $pair) {
                if ($id($key) && is_array($pair) && count($pair) === 2) {
                    $decalages[$format][$key] = [(int) round($num($pair[0] ?? 0, -3000, 3000)), (int) round($num($pair[1] ?? 0, -3000, 3000))];
                }
            }
        }

        $tailles = [];
        foreach ((array) ($raw['tailles'] ?? []) as $key => $factor) {
            if ($id($key)) {
                $tailles[$key] = $num($factor, 0.4, 2.6, 1);
            }
        }

        $libres = [];
        foreach (array_slice((array) ($raw['libres'] ?? []), 0, self::MAX_LIBRES) as $e) {
            if (! is_array($e) || ! $id($e['id'] ?? null) || ! in_array($e['type'] ?? '', ['texte', 'pastille', 'forme', 'ligne', 'logo', 'photo'], true)) {
                continue;
            }
            $item = [
                'id' => $e['id'], 'type' => $e['type'],
                'x' => $num($e['x'] ?? 0.1, -0.5, 1.5), 'y' => $num($e['y'] ?? 0.1, -0.5, 1.5),
                'w' => $num($e['w'] ?? 0.3, 0.01, 2, 0.3),
            ];
            if (isset($e['h'])) {
                $item['h'] = $num($e['h'], 0.01, 2, 0.1);
            }
            if (isset($e['opacite'])) {
                $item['opacite'] = $num($e['opacite'], 0, 1, 1);
            }
            if (in_array($e['type'], ['texte', 'pastille'], true)) {
                $item += [
                    'texte' => mb_substr(trim(strip_tags((string) ($e['texte'] ?? ''))), 0, 300),
                    'taille' => $num($e['taille'] ?? 0.04, 0.01, 0.3, 0.04),
                    'couleur' => $color($e['couleur'] ?? null, '#ffffff'),
                    'poids' => (int) min(900, max(100, (int) ($e['poids'] ?? 700))),
                    'majuscules' => (bool) ($e['majuscules'] ?? false),
                ];
                if ($e['type'] === 'texte') {
                    $item['align'] = in_array($e['align'] ?? '', ['gauche', 'centre', 'droite'], true) ? $e['align'] : 'gauche';
                } else {
                    $item['fond'] = $color($e['fond'] ?? null, '#00c4d4');
                    $item['espacement'] = $num($e['espacement'] ?? 0.08, 0, 0.5, 0.08);
                }
            } elseif ($e['type'] === 'forme') {
                $item += ['kind' => ($e['kind'] ?? '') === 'cercle' ? 'cercle' : 'rect', 'couleur' => $color($e['couleur'] ?? null, '#00c4d4'), 'rayon' => $num($e['rayon'] ?? 0, 0, 0.3), 'contour' => (bool) ($e['contour'] ?? false)];
            } elseif ($e['type'] === 'ligne') {
                $item += ['epaisseur' => $num($e['epaisseur'] ?? 4, 1, 40, 4), 'couleur' => $color($e['couleur'] ?? null, '#00c4d4')];
            } elseif ($e['type'] === 'logo') {
                $item['logo'] = ($e['logo'] ?? '') === 'theme' ? 'theme' : 'sceau';
            } elseif ($e['type'] === 'photo') {
                $item['slot'] = is_string($e['slot'] ?? null) && preg_match('/^[a-z0-9_]{1,24}$/i', $e['slot']) ? $e['slot'] : 'lp'.count($libres);
            }
            $libres[] = $item;
        }

        return ['masques' => $masques, 'decalages' => $decalages, 'tailles' => $tailles, 'libres' => $libres];
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
            'data' => ['champs' => (array) ($design->data['champs'] ?? []), 'photos' => $photos, 'options' => (array) ($design->data['options'] ?? [])],
        ];
    }
}
