<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PublicationStatus;
use App\Filament\Forms\MediaField;
use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Support\EditMode;
use App\Support\VisualBlocks;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Éditeur visuel des pages : on modifie la page elle-même (textes en place, blocs ajoutés, déplacés, dupliqués) dans un brouillon ;
 * la page publiée n'est touchée qu'au clic sur « Publier ». Accès : mêmes droits que le formulaire classique (politique « update »).
 * Les pages à mise en page sur mesure (VisualBlocks::BESPOKE_KEYS) n'ont pas de blocs à éditer : un écran l'explique.
 */
class VisualEditorController extends Controller
{
    public function show(Request $request, Page $page): View|RedirectResponse
    {
        if (! $request->user()) {
            return redirect('/admin/login');
        }
        $this->authorizeEdit($request, $page);

        $classic = url('/admin/content-pages/pages/'.$page->id.'/edit');

        if (VisualBlocks::isBespoke($page)) {
            return view('admin.visual.bespoke', ['page' => $page, 'classic' => $classic]);
        }

        $draft = $page->draft;
        $state = [
            'title' => $draft['title'] ?? $page->title,
            'summary' => $draft['summary'] ?? $page->summary ?? '',
            'blocks' => $draft['blocks'] ?? $page->blocks ?? [],
        ];

        return view('admin.visual.editor', [
            'page' => $page,
            'boot' => [
                'pageId' => $page->id,
                'pageTitle' => $page->title,
                'status' => $page->status->value,
                'state' => $state,
                'media' => $this->mediaMap($state['blocks']),
                'hasDraft' => $draft !== null,
                'savedAt' => $draft['saved_at'] ?? null,
                'canPublish' => $this->canPublish($request, $page),
                'types' => VisualBlocks::types(),
                'urls' => [
                    'canvas' => route('admin.visual-editor.canvas', $page),
                    'render' => route('admin.visual-editor.render', $page),
                    'draft' => route('admin.visual-editor.draft', $page),
                    'publish' => route('admin.visual-editor.publish', $page),
                    'preview' => route('admin.visual-editor.preview', $page),
                    'classic' => $classic,
                    'admin' => url('/admin/content-pages/pages'),
                    'media' => route('admin.media-browser.browse'),
                    'upload' => route('admin.media-browser.upload'),
                    'live' => $page->url(),
                ],
            ],
        ]);
    }

    /** Document vide dans lequel l'éditeur dessine la page (aucun script du site public : pas d'animations pendant l'édition). */
    public function canvas(Request $request, Page $page): View
    {
        $this->authorizeEdit($request, $page);

        return view('admin.visual.canvas');
    }

    /** Dessine la page (en-tête et blocs) pour l'état envoyé, sans rien enregistrer. */
    public function render(Request $request, Page $page): JsonResponse
    {
        $this->authorizeEdit($request, $page);
        $state = $this->validated($request, strict: false);

        EditMode::enable();
        try {
            $html = view('admin.visual._content', ['page' => $page] + $state)->render();
        } finally {
            EditMode::disable();
        }

        return response()->json(['html' => $html, 'media' => $this->mediaMap($state['blocks'])]);
    }

    /**
     * Vignettes des fichiers utilisés par les blocs (pour afficher les images choisies dans le panneau de réglages).
     *
     * @param  list<array<string, mixed>>  $blocks
     * @return array<int, array<string, mixed>>
     */
    private function mediaMap(array $blocks): array
    {
        $ids = VisualBlocks::mediaIds($blocks);

        return $ids
            ? MediaAsset::query()->with('media')->whereIn('id', $ids)->get()->mapWithKeys(fn (MediaAsset $a) => [$a->id => MediaField::describe($a)])->all()
            : [];
    }

    public function saveDraft(Request $request, Page $page): JsonResponse
    {
        $this->authorizeEdit($request, $page);
        $state = $this->validated($request, strict: false);
        $savedAt = now()->toIso8601String();

        $page->forceFill(['draft' => $state + ['saved_at' => $savedAt, 'user_id' => $request->user()->id]])->saveQuietly();

        return response()->json(['saved_at' => $savedAt]);
    }

    public function discardDraft(Request $request, Page $page): JsonResponse
    {
        $this->authorizeEdit($request, $page);
        $page->forceFill(['draft' => null])->saveQuietly();

        return response()->json(['discarded' => true]);
    }

    /** Applique le brouillon à la page (mode strict : champs obligatoires remplis). */
    public function publish(Request $request, Page $page): JsonResponse
    {
        $this->authorizeEdit($request, $page);
        abort_unless($this->canPublish($request, $page), 403, 'Vous ne pouvez pas publier cette page.');

        $state = $this->validated($request, strict: true);
        $page->forceFill(['draft' => null])->fill($state)->save();

        return response()->json(['published' => true, 'url' => $page->url()]);
    }

    /** Aperçu fidèle : la vraie mise en page du site, avec le brouillon, hors de l'éditeur. */
    public function preview(Request $request, Page $page): View
    {
        $this->authorizeEdit($request, $page);

        $draft = $page->draft ?? [];
        $page->title = $draft['title'] ?? $page->title;
        $page->summary = $draft['summary'] ?? $page->summary;
        $page->blocks = $draft['blocks'] ?? $page->blocks;

        $trail = [];
        for ($parent = $page->parent; $parent; $parent = $parent->parent) {
            array_unshift($trail, ['label' => $parent->title, 'url' => $parent->url()]);
        }
        $trail[] = ['label' => $page->title];

        return view('pages.show', ['page' => $page, 'trail' => $trail, 'children' => collect()]);
    }

    /** @return array{title: string, summary: string, blocks: list<array<string, mixed>>} */
    private function validated(Request $request, bool $strict): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'summary' => ['nullable', 'string', 'max:500'],
            'blocks' => ['present', 'array'],
        ]);

        return [
            'title' => trim($data['title']),
            'summary' => trim((string) ($data['summary'] ?? '')),
            'blocks' => VisualBlocks::normalize($data['blocks'], $strict),
        ];
    }

    private function authorizeEdit(Request $request, Page $page): void
    {
        abort_unless($request->user()?->can('update', $page), 403);
    }

    private function canPublish(Request $request, Page $page): bool
    {
        // Une page non publiée peut être modifiée par quiconque peut l'éditer ; une page publiée demande le droit de publier.
        return $page->status !== PublicationStatus::Published || $request->user()?->can('content.publish');
    }
}
