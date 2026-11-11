<?php

namespace App\Http\Controllers\Admin;

use App\Filament\Forms\MediaField;
use App\Http\Controllers\Controller;
use App\Mail\NewsletterMessage;
use App\Models\MediaAsset;
use App\Models\Newsletter;
use App\Support\NewsletterBlocks;
use App\Support\NewsletterRenderer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * Éditeur visuel des lettres d'information : la lettre s'affiche comme elle partira (plan de travail), on modifie les textes sur place,
 * on ajoute, déplace, duplique et supprime des blocs, on règle les couleurs. Le navigateur envoie l'état de la lettre ; le serveur le
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
                'palette' => NewsletterBlocks::PALETTE,
                'media' => $this->mediaMap($state['blocks']),
                'defaults' => NewsletterBlocks::defaultTheme(),
                'email' => $request->user()->email,
                'urls' => [
                    'render' => route('admin.newsletter-editor.render', $newsletter),
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

    /** Dessine la lettre pour l'état envoyé, sans rien enregistrer : c'est le contenu du plan de travail. */
    public function render(Request $request, Newsletter $newsletter): JsonResponse
    {
        abort_unless($request->user(), 401);
        abort_unless($request->user()->can('view', $newsletter), 403);
        $state = $this->state($request);
        $selected = $request->integer('selected', -1);
        // Repères de modification seulement pour un brouillon qu'on a le droit de modifier ; une lettre envoyée s'affiche telle quelle.
        $editing = $this->canEdit($request, $newsletter);

        return response()->json([
            'html' => NewsletterRenderer::html($state, null, null, $editing, $editing && $selected >= 0 ? $selected : null),
            'blocks' => $state['blocks'],
            'media' => $this->mediaMap($state['blocks']),
        ]);
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

    /** Envoi d'essai à une adresse (sans toucher aux abonnés ni à la lettre). */
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
            return response()->json(['message' => 'Donnez un objet à la lettre avant de l\'envoyer.'], 422);
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

    /** Un brouillon, et le droit de publier. Le super-administrateur contourne les politiques : on ne s'en remet pas à elles pour figer une lettre envoyée. */
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
     * Images citées par les blocs (miniatures du panneau de réglages).
     *
     * @param  list<array<string, mixed>>  $blocks
     * @return array<int, array<string, mixed>>
     */
    private function mediaMap(array $blocks): array
    {
        $ids = collect($blocks)->pluck('data.media')->filter()->unique();

        return MediaAsset::with('media')->whereIn('id', $ids)->get()->mapWithKeys(fn (MediaAsset $asset) => [$asset->id => MediaField::describe($asset)])->all();
    }
}
