<?php

namespace App\Mail;

use App\Models\ContactMessage;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Notification interne : un visiteur a écrit via le formulaire de contact. */
class ContactMessageReceived extends Mailable
{
    public function __construct(public ContactMessage $contact) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            // L'expéditeur reste l'adresse du site ; la réponse part directement vers le visiteur.
            replyTo: [new Address($this->contact->email, $this->contact->name)],
            subject: '[Site UAZ] '.$this->contact->subject,
        );
    }

    public function content(): Content
    {
        return new Content(text: 'mail.contact-received');
    }
}
