<?php

namespace App\Jobs;

use App\Models\Newsletter;
use App\Models\NewsletterSubscriber;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Lance l'envoi d'une newsletter : un travail par abonné actif (App\Jobs\SendNewsletterMessage), espacés de quelques secondes pour ne pas dépasser
 * les limites d'envoi de l'hébergeur (config/newsletter.php). Relançable sans risque : un abonné qui a déjà reçu la newsletter est ignoré.
 */
class SendNewsletter implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $newsletterId) {}

    public function handle(): void
    {
        $newsletter = Newsletter::find($this->newsletterId);

        if (! $newsletter || $newsletter->status !== Newsletter::SENDING) {
            return;
        }

        $gap = (int) config('newsletter.seconds_between_messages');
        $position = 0;

        NewsletterSubscriber::active()->orderBy('id')->chunkById(200, function ($subscribers) use ($newsletter, $gap, &$position): void {
            foreach ($subscribers as $subscriber) {
                SendNewsletterMessage::dispatch($newsletter->id, $subscriber->id)->delay(now()->addSeconds($position++ * $gap));
            }
        });
    }
}
