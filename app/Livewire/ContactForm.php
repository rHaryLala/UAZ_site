<?php

namespace App\Livewire;

use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Formulaire de contact. Protections : validation serveur, jeton CSRF (Livewire), limitation de débit par IP,
 * champ piège (honeypot) invisible et délai minimal de remplissage.
 */
class ContactForm extends Component
{
    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $subject = '';

    public string $message = '';

    /** Honeypot : un humain ne le voit jamais ; un robot le remplit. */
    public string $website = '';

    /** Horodatage d'affichage du formulaire (délai minimal anti-robot) : verrouillé, le navigateur ne peut pas le modifier. */
    #[Locked]
    public int $renderedAt = 0;

    #[Locked]
    public bool $sent = false;

    public function mount(): void
    {
        $this->renderedAt = now()->timestamp;
        $this->subject = (string) request()->query('sujet', '');
    }

    /** @return array<string, array<int, string>> */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+().\s\-]{6,40}$/'],
            'subject' => ['required', 'string', 'min:3', 'max:150'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'name' => 'nom',
            'email' => 'adresse e-mail',
            'phone' => 'téléphone',
            'subject' => 'sujet',
            'message' => 'message',
        ];
    }

    public function send(): void
    {
        $key = 'contact:'.request()->ip();
        $limit = (int) config('uaz.contact.rate_limit_per_minute');

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            $this->addError('message', 'Trop de messages envoyés en peu de temps. Réessayez dans '.RateLimiter::availableIn($key).' secondes.');

            return;
        }

        $data = $this->validate();

        RateLimiter::hit($key, 60);

        // Robot détecté : on simule un succès sans rien enregistrer ni envoyer.
        if ($this->website !== '' || now()->timestamp - $this->renderedAt < (int) config('uaz.contact.min_fill_seconds')) {
            $this->finish();

            return;
        }

        $contact = ContactMessage::create([
            ...$data,
            'phone' => $data['phone'] ?: null,
            'ip_hash' => hash('sha256', request()->ip().config('app.key')),
            'user_agent' => mb_substr((string) request()->userAgent(), 0, 250),
        ]);

        $recipient = config('uaz.contact.recipient') ?: SiteSetting::current()->email;

        if ($recipient) {
            Mail::to($recipient)->send(new ContactMessageReceived($contact));
        }

        $this->finish();
    }

    /** « Envoyer un autre message » : `sent` est verrouillé côté navigateur, on passe donc par une méthode. */
    public function another(): void
    {
        $this->sent = false;
        $this->renderedAt = now()->timestamp;
    }

    private function finish(): void
    {
        $this->reset(['name', 'email', 'phone', 'subject', 'message', 'website']);
        $this->sent = true;
    }

    public function render(): View
    {
        return view('livewire.contact-form');
    }
}
