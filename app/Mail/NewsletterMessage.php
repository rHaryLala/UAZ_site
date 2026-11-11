<?php

namespace App\Mail;

use App\Models\Newsletter;
use App\Models\NewsletterSubscriber;
use App\Models\SiteSetting;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Facades\URL;

/**
 * Une lettre d'information, pour UN abonné (lien de désinscription personnel et signé) ou pour un envoi d'essai ($subscriber = null).
 * Le courrier est dessiné par App\Support\NewsletterRenderer, comme l'aperçu de l'éditeur. L'envoi n'est pas mis en file ici : c'est le travail
 * App\Jobs\SendNewsletterMessage qui l'est (un par abonné), pour tracer chaque envoi.
 */
class NewsletterMessage extends Mailable
{
    public function __construct(public Newsletter $newsletter, public ?NewsletterSubscriber $subscriber = null, public bool $test = false) {}

    private function unsubscribeUrl(): ?string
    {
        return $this->subscriber ? URL::signedRoute('newsletter.unsubscribe', ['subscriber' => $this->subscriber]) : null;
    }

    public function envelope(): Envelope
    {
        $reply = SiteSetting::current()->email;

        return new Envelope(
            from: new Address((string) config('newsletter.from.address'), (string) config('newsletter.from.name')),
            subject: ($this->test ? '[TEST] ' : '').$this->newsletter->subject,
            replyTo: $reply ? [new Address($reply, 'Université Adventiste Zurcher')] : [],
        );
    }

    public function content(): Content
    {
        $unsubscribe = $this->unsubscribeUrl();

        return new Content(
            htmlString: $this->newsletter->html($this->subscriber, $unsubscribe),
            text: 'mail.newsletter-plain',
            with: ['text' => $this->newsletter->plainText($unsubscribe)],
        );
    }

    /** En-tête « List-Unsubscribe » : les messageries affichent un bouton « Se désinscrire » (et jugent mieux le message). */
    public function headers(): Headers
    {
        $url = $this->unsubscribeUrl();

        return new Headers(text: $url ? ['List-Unsubscribe' => '<'.$url.'>'] : []);
    }
}
