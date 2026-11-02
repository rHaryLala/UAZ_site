<?php

namespace App\Mail;

use App\Models\NewsletterSubscriber;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\URL;

/** E-mail de confirmation (double consentement) envoyé à la personne qui demande à s'abonner. */
class NewsletterConfirmation extends Mailable
{
    public function __construct(public NewsletterSubscriber $subscriber) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Confirmez votre abonnement à la lettre d\'information de l\'UAZ'));
    }

    public function content(): Content
    {
        return new Content(text: 'mail.newsletter-confirmation', with: [
            'confirmUrl' => URL::temporarySignedRoute('newsletter.confirm', now()->addDays(7), ['subscriber' => $this->subscriber]),
            'unsubscribeUrl' => URL::signedRoute('newsletter.unsubscribe', ['subscriber' => $this->subscriber]),
        ]);
    }
}
