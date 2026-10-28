<?php

namespace App\Http\Controllers;

use App\Mail\NewsletterConfirmation;
use App\Models\NewsletterSubscriber;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * Lettre d'information : demande d'abonnement depuis la fenêtre du site (réponse JSON), puis confirmation et désinscription par liens signés.
 * Protections : validation, limitation de débit (route), champ piège, double confirmation par e-mail (personne ne peut inscrire une adresse tierce).
 */
class NewsletterController extends Controller
{
    /** Délai minimal avant de renvoyer un e-mail de confirmation à la même adresse. */
    private const RESEND_AFTER_MINUTES = 10;

    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate(
            ['email' => ['required', 'string', 'email:rfc', 'max:190'], 'website' => ['nullable', 'string', 'max:200']],
            [],
            ['email' => 'adresse e-mail'],
        );

        $message = 'Merci ! Un e-mail de confirmation vient de vous être envoyé : cliquez sur le lien qu\'il contient pour finaliser votre abonnement.';

        // Robot détecté (champ piège rempli) : on simule un succès sans rien enregistrer.
        if (filled($data['website'] ?? null)) {
            return response()->json(['message' => $message]);
        }

        $subscriber = NewsletterSubscriber::firstOrNew(['email' => mb_strtolower(trim($data['email']))]);

        // Déjà abonné : même réponse (on ne révèle pas qui est inscrit) et aucun nouvel e-mail.
        if ($subscriber->status() === 'active') {
            return response()->json(['message' => $message]);
        }

        // Confirmation déjà envoyée il y a quelques minutes : pas de doublon.
        if ($subscriber->unsubscribed_at === null && $subscriber->confirmation_sent_at?->gt(now()->subMinutes(self::RESEND_AFTER_MINUTES))) {
            return response()->json(['message' => $message]);
        }

        $subscriber->fill(['confirmed_at' => null, 'unsubscribed_at' => null, 'confirmation_sent_at' => now()])->save();

        try {
            Mail::to($subscriber->email)->send(new NewsletterConfirmation($subscriber));
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'L\'e-mail de confirmation n\'a pas pu être envoyé. Réessayez dans quelques instants.'], 503);
        }

        return response()->json(['message' => $message]);
    }

    public function confirm(NewsletterSubscriber $subscriber): View
    {
        $subscriber->forceFill(['confirmed_at' => $subscriber->confirmed_at ?? now(), 'unsubscribed_at' => null])->save();

        return view('newsletter.status', [
            'title' => 'Abonnement confirmé',
            'text' => 'Merci ! Votre adresse est bien inscrite à la lettre d\'information de l\'UAZ. Vous pouvez vous désinscrire à tout moment depuis le lien présent dans nos messages.',
        ]);
    }

    public function unsubscribe(NewsletterSubscriber $subscriber): View
    {
        $subscriber->forceFill(['unsubscribed_at' => $subscriber->unsubscribed_at ?? now()])->save();

        return view('newsletter.status', [
            'title' => 'Désinscription effectuée',
            'text' => 'Votre adresse a été retirée de la lettre d\'information de l\'UAZ. Vous ne recevrez plus de message de notre part.',
        ]);
    }
}
