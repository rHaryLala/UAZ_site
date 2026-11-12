<?php

namespace App\Http\Controllers;

use App\Models\NewsletterSubscriber;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lettre d'information : abonnement depuis la fenêtre du site (réponse JSON) et désinscription par lien signé.
 *
 * L'abonnement est IMMÉDIAT : un clic sur « S'abonner » suffit, aucun e-mail de confirmation n'est envoyé (décision de l'UAZ, voir D-073).
 * Contrepartie : n'importe qui peut saisir l'adresse de quelqu'un d'autre. Protections : validation, limitation de débit (route),
 * champ piège contre les robots, lien de désinscription en un clic dans chaque lettre, et suppression possible depuis le back-office.
 */
class NewsletterController extends Controller
{
    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate(
            ['email' => ['required', 'string', 'email:rfc', 'max:190'], 'website' => ['nullable', 'string', 'max:200']],
            [],
            ['email' => __('adresse e-mail')],
        );

        $message = __('Merci ! Vous êtes abonné(e) à la lettre d\'information de l\'UAZ.');

        // Robot détecté (champ piège rempli) : on simule un succès sans rien enregistrer.
        if (filled($data['website'] ?? null)) {
            return response()->json(['message' => $message]);
        }

        $subscriber = NewsletterSubscriber::firstOrNew(['email' => mb_strtolower(trim($data['email']))]);

        // Déjà abonné : même réponse, rien à faire (on ne révèle pas qui est inscrit).
        if ($subscriber->status() !== 'active') {
            // Nouvelle adresse, adresse restée « non confirmée » (import) ou personne qui revient après s'être désinscrite : demande explicite, abonnement immédiat.
            $subscriber->forceFill(['confirmed_at' => now(), 'unsubscribed_at' => null])->save();
        }

        return response()->json(['message' => $message]);
    }

    public function unsubscribe(NewsletterSubscriber $subscriber): View
    {
        $subscriber->forceFill(['unsubscribed_at' => $subscriber->unsubscribed_at ?? now()])->save();

        return view('newsletter.status', [
            'title' => __('Désinscription effectuée'),
            'text' => __('Votre adresse a été retirée de la lettre d\'information de l\'UAZ. Vous ne recevrez plus de message de notre part.'),
        ]);
    }
}
