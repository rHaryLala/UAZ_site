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
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\AlternativePart;
use Symfony\Component\Mime\Part\Multipart\RelatedPart;
use Symfony\Component\Mime\Part\TextPart;

/**
 * Une lettre d'information, pour UN abonné (lien de désinscription personnel et signé) ou pour un envoi d'essai ($subscriber = null).
 * Le courrier est dessiné par App\Support\NewsletterRenderer, comme l'aperçu de l'éditeur. L'envoi n'est pas mis en file ici : c'est le travail
 * App\Jobs\SendNewsletterMessage qui l'est (un par abonné), pour tracer chaque envoi.
 */
class NewsletterMessage extends Mailable
{
    /** @var array<string, string> images à joindre : identifiant (cid) => fichier */
    private array $embeds = [];

    public function __construct(public Newsletter $newsletter, public ?NewsletterSubscriber $subscriber = null, public bool $test = false)
    {
        // Les images repérées au dessin du courrier sont jointes au message au moment de l'envoi.
        $this->withSymfonyMessage(function (Email $message): void {
            if ($this->embeds === []) {
                return;
            }

            // Structure attendue par les messageries : « texte brut OU (HTML + ses images) ». Symfony, lui, range les images à côté du bloc
            // texte/HTML : Gmail les prend alors pour des pièces jointes et les affiche en pastilles sous l'objet. Les images n'ont pas non plus de
            // nom de fichier (un nom suffit à les faire lister).
            $images = [];

            foreach ($this->embeds as $cid => $path) {
                $images[] = (new DataPart((string) file_get_contents($path), null, (string) mime_content_type($path)))->setContentId($cid.'@uaz.site')->asInline();
            }

            $related = new RelatedPart(new TextPart((string) $message->getHtmlBody(), 'utf-8', 'html'), ...$images);
            $text = $message->getTextBody();
            $message->setBody($text !== null && $text !== '' ? new AlternativePart(new TextPart((string) $text, 'utf-8', 'plain'), $related) : $related);
        });
    }

    /** Joint-on les images ? Voir config/newsletter.php (« embed_images »). */
    private function shouldEmbed(): bool
    {
        $setting = config('newsletter.embed_images');

        if ($setting !== null && $setting !== '') {
            return filter_var($setting, FILTER_VALIDATE_BOOLEAN);
        }

        $host = (string) parse_url(url('/'), PHP_URL_HOST);

        return in_array($host, ['localhost', '127.0.0.1', '::1'], true) || str_ends_with($host, '.test') || str_ends_with($host, '.local');
    }

    /**
     * Remplace chaque image hébergée par ce site (et présente sur le disque) par une pièce jointe intégrée (« cid: »). Les images d'autres sites
     * et les liens ne sont pas touchés.
     */
    private function embedLocalImages(string $html): string
    {
        $base = rtrim(url('/'), '/');
        $this->embeds = [];

        return (string) preg_replace_callback('~\ssrc="('.preg_quote($base, '~').'/[^"?#]+)(?:[?#][^"]*)?"~', function (array $match) use ($base): string {
            $path = public_path(rawurldecode(ltrim(substr($match[1], strlen($base)), '/')));

            if (! is_file($path)) {
                return $match[0];
            }

            $cid = md5($path);
            $this->embeds[$cid] = $path;

            return ' src="cid:'.$cid.'@uaz.site"';
        }, $html);
    }

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

        $html = $this->newsletter->html($this->subscriber, $unsubscribe);

        return new Content(
            htmlString: $this->shouldEmbed() ? $this->embedLocalImages($html) : $html,
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
