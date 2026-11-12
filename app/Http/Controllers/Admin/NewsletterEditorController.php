<?php

namespace App\Http\Controllers\Admin;

use App\Filament\Forms\MediaField;
use App\Http\Controllers\Controller;
use App\Mail\NewsletterMessage;
use App\Models\MediaAsset;
use App\Models\Newsletter;
use App\Models\SiteSetting;
use App\Support\NewsletterBlocks;
use App\Support\NewsletterRenderer;
use App\Support\NewsletterTemplates;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * Éditeur visuel des newsletters : la newsletter s'affiche comme elle partira (plan de travail), on modifie les textes sur place,
 * on ajoute, déplace, duplique et supprime des blocs, on règle les couleurs. Le navigateur envoie l'état de la newsletter ; le serveur le
 * valide (App\Support\NewsletterBlocks::normalize), le dessine (App\Support\NewsletterRenderer) et l'enregistre : il n'est jamais cru sur parole.
 * Droits : politique NewsletterPolicy (brouillon et « content.publish »).
 */
class NewsletterEditorController extends Controller
{
    public function show(Request $request, Newsletter $newsletter): View|RedirectResponse
    {
        if (! $request->user()) {
            return redirect('/admin/login');
        }

        abort_unless($request->user()->can('view', $newsletter), 403);

        $state = $newsletter->state();

        return view('admin.newsletter.editor', [
            'newsletter' => $newsletter,
            'boot' => [
                'id' => $newsletter->id,
                'locked' => ! $newsletter->isDraft(),
                'status' => $newsletter->status,
                'sentAt' => $newsletter->sent_at?->translatedFormat('j F Y à H:i'),
                'recipients' => $newsletter->recipients_count,
                'audience' => Newsletter::audienceCount(),
                'state' => $state,
                'types' => NewsletterBlocks::types(),
                'library' => $this->library(),
                'ratios' => NewsletterBlocks::RATIOS,
                'max' => NewsletterBlocks::MAX_BLOCKS,
                'maxDepth' => NewsletterBlocks::MAX_DEPTH,
                'from' => config('newsletter.from.address'),
                'palette' => NewsletterBlocks::PALETTE,
                'media' => $this->mediaMap($state['blocks']),
                'defaults' => NewsletterBlocks::defaultTheme(),
                'header' => [
                    'items' => NewsletterBlocks::HEADER_ITEMS,
                    'slots' => NewsletterBlocks::SLOTS,
                    'socials' => NewsletterBlocks::SOCIALS,
                    'site' => array_filter(SiteSetting::current()->social_links ?? []),
                    'uaz' => NewsletterBlocks::modernHeader(SiteSetting::current()->social_links ?? []),
                ],
                'email' => $request->user()->email,
                'urls' => [
                    'render' => route('admin.newsletter-editor.render', $newsletter),
                    'rows' => route('admin.newsletter-editor.rows', $newsletter),
                    'header' => route('admin.newsletter-editor.header', $newsletter),
                    'templates' => route('admin.newsletter-editor.templates', $newsletter),
                    'save' => route('admin.newsletter-editor.save', $newsletter),
                    'test' => route('admin.newsletter-editor.test', $newsletter),
                    'send' => route('admin.newsletter-editor.send', $newsletter),
                    'list' => url('/admin/newsletters'),
                    'media' => route('admin.media-browser.browse'),
                    'upload' => route('admin.media-browser.upload'),
                ],
            ],
        ]);
    }

    /** Dessine la newsletter pour l'état envoyé, sans rien enregistrer : c'est le contenu du plan de travail. */
    public function render(Request $request, Newsletter $newsletter): JsonResponse
    {
        abort_unless($request->user(), 401);
        abort_unless($request->user()->can('view', $newsletter), 403);
        $state = $this->state($request);
        // Repères de modification seulement pour un brouillon qu'on a le droit de modifier ; une newsletter envoyée s'affiche telle quelle.
        $editing = $this->canEdit($request, $newsletter);

        return response()->json([
            'html' => NewsletterRenderer::html($state, null, null, $editing),
            'blocks' => $state['blocks'],
            'media' => $this->mediaMap($state['blocks']),
        ]);
    }

    /**
     * Dessine quelques blocs de premier niveau (une ligne de la newsletter chacun) : le plan de travail ne redessine que ce qui a changé,
     * au lieu de recharger toute la newsletter. Sert aussi à préparer d'avance les blocs du panneau « Ajouter ».
     */
    public function rows(Request $request, Newsletter $newsletter): JsonResponse
    {
        abort_unless($request->user(), 401);
        abort_unless($request->user()->can('view', $newsletter), 403);
        $data = $request->validate(['blocks' => ['required', 'array', 'max:40'], 'theme' => ['nullable', 'array']]);
        $blocks = NewsletterBlocks::normalize($data['blocks']);

        return response()->json(['rows' => NewsletterRenderer::rows($blocks, NewsletterBlocks::theme($data['theme'] ?? null)), 'media' => $this->mediaMap($blocks)]);
    }

    /** Dessine l'en-tête seul (lignes du bandeau) : réglé en direct, il n'oblige pas à recharger toute la newsletter. */
    public function header(Request $request, Newsletter $newsletter): JsonResponse
    {
        abort_unless($request->user(), 401);
        abort_unless($request->user()->can('view', $newsletter), 403);
        $data = $request->validate(['theme' => ['nullable', 'array']]);

        return response()->json(['html' => NewsletterRenderer::header(NewsletterBlocks::theme($data['theme'] ?? null), true)]);
    }

    /** Les modèles de newsletter, chacun avec son aperçu (la newsletter entière, telle qu'elle partirait), pour choisir sans deviner. */
    public function templates(Request $request, Newsletter $newsletter): JsonResponse
    {
        abort_unless($request->user(), 401);
        abort_unless($request->user()->can('view', $newsletter), 403);

        $items = [];
        $names = NewsletterTemplates::list();

        foreach (NewsletterTemplates::keys() as $key) {
            $built = NewsletterTemplates::build($key);
            $items[] = [
                'key' => $key,
                'name' => $names[$key]['name'],
                'description' => $names[$key]['description'],
                'subject' => $built['subject'],
                'theme' => $built['theme'],
                'blocks' => $built['blocks'],
                'preview' => NewsletterRenderer::html($built),
                'media' => $this->mediaMap($built['blocks']),
            ];
        }

        return response()->json(['templates' => $items]);
    }

    /** Enregistrement automatique du brouillon. */
    public function save(Request $request, Newsletter $newsletter): JsonResponse
    {
        $this->authorizeUpdate($request, $newsletter);
        $state = $this->state($request);

        $newsletter->forceFill([
            'subject' => $state['subject'],
            'preheader' => $state['preheader'] ?: null,
            'blocks' => $state['blocks'],
            'theme' => $state['theme'],
        ])->save();

        return response()->json(['saved_at' => now()->format('H:i:s')]);
    }

    /** Envoi d'essai à une adresse (sans toucher aux abonnés ni à la newsletter). */
    public function test(Request $request, Newsletter $newsletter): JsonResponse
    {
        $this->authorizeUpdate($request, $newsletter);
        $data = $request->validate(['to' => ['required', 'email:rfc', 'max:190']]);
        $state = $this->state($request);

        $copy = $newsletter->replicate();
        $copy->forceFill(['subject' => $state['subject'] ?: 'Sans objet', 'preheader' => $state['preheader'], 'blocks' => $state['blocks'], 'theme' => $state['theme']]);

        try {
            Mail::to($data['to'])->send(new NewsletterMessage($copy, null, test: true));
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'L\'envoi d\'essai a échoué : vérifiez la configuration du courrier (docs/DEPLOIEMENT.md).'], 503);
        }

        return response()->json(['message' => 'Essai envoyé à '.$data['to'].'.']);
    }

    /** Enregistre l'état final puis lance l'envoi à tous les abonnés actifs. */
    public function send(Request $request, Newsletter $newsletter): JsonResponse
    {
        $this->authorizeUpdate($request, $newsletter);
        $state = $this->state($request);

        if ($state['subject'] === '') {
            return response()->json(['message' => 'Donnez un objet à la newsletter avant de l\'envoyer.'], 422);
        }

        $newsletter->forceFill([
            'subject' => $state['subject'],
            'preheader' => $state['preheader'] ?: null,
            'blocks' => $state['blocks'],
            'theme' => $state['theme'],
        ])->save();

        try {
            $newsletter->send();
        } catch (\LogicException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'L\'envoi est lancé.', 'recipients' => $newsletter->fresh()->recipients_count, 'list' => url('/admin/newsletters')]);
    }

    private function authorizeUpdate(Request $request, Newsletter $newsletter): void
    {
        abort_unless($request->user(), 401);
        abort_unless($this->canEdit($request, $newsletter), 403);
    }

    /** Un brouillon, et le droit de publier. Le super-administrateur contourne les politiques : on ne s'en remet pas à elles pour figer une newsletter envoyée. */
    private function canEdit(Request $request, Newsletter $newsletter): bool
    {
        return $newsletter->isDraft() && $request->user()?->can('update', $newsletter) === true;
    }

    /** @return array{subject: string, preheader: string, blocks: array, theme: array} */
    private function state(Request $request): array
    {
        $data = $request->validate([
            'subject' => ['nullable', 'string', 'max:190'],
            'preheader' => ['nullable', 'string', 'max:190'],
            'blocks' => ['nullable', 'array', 'max:'.NewsletterBlocks::MAX_BLOCKS],
            'theme' => ['nullable', 'array'],
        ]);

        return [
            'subject' => trim(strip_tags((string) ($data['subject'] ?? ''))),
            'preheader' => trim(strip_tags((string) ($data['preheader'] ?? ''))),
            'blocks' => NewsletterBlocks::normalize($data['blocks'] ?? []),
            'theme' => NewsletterBlocks::theme($data['theme'] ?? null),
        ];
    }

    /**
     * Les blocs du panneau « Ajouter » : les types (avec leurs blocs de départ) puis les compositions toutes faites, chacun avec un bloc modèle
     * que l'éditeur copie (identifiants renouvelés) et dessine d'avance.
     *
     * @return list<array<string, mixed>>
     */
    private function library(): array
    {
        $items = [];

        foreach (NewsletterBlocks::types() as $type => $meta) {
            $items[] = ['kind' => 'type', 'key' => $type, 'label' => $meta['label'], 'hint' => $meta['hint'], 'group' => $meta['group'], 'block' => NewsletterBlocks::make($type)];
        }

        foreach (NewsletterBlocks::presets() as $key => $preset) {
            $items[] = ['kind' => 'preset', 'key' => $key, 'label' => $preset['label'], 'hint' => $preset['hint'], 'group' => 'preset', 'block' => NewsletterBlocks::withIds([$preset['block']])[0]];
        }

        return $items;
    }

    /**
     * Images citées par les blocs (miniatures du panneau de réglages).
     *
     * @param  list<array<string, mixed>>  $blocks
     * @return array<int, array<string, mixed>>
     */
    private function mediaMap(array $blocks): array
    {
        return MediaAsset::with('media')->whereIn('id', NewsletterBlocks::mediaIds($blocks))->get()->mapWithKeys(fn (MediaAsset $asset) => [$asset->id => MediaField::describe($asset)])->all();
    }
}
