<?php

namespace App\Jobs;

use App\Mail\NewsletterMessage;
use App\Models\Newsletter;
use App\Models\NewsletterDelivery;
use App\Models\NewsletterSubscriber;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

/**
 * Envoie une lettre à UN abonné et note le résultat (newsletter_deliveries). Le couple (lettre, abonné) est unique : même relancé, ce travail
 * n'envoie jamais deux fois. Un abonné désinscrit entre-temps est ignoré. En cas d'échec, la file réessaie (1 puis 5 minutes), puis l'échec est noté.
 */
class SendNewsletterMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $newsletterId, public int $subscriberId) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(): void
    {
        $newsletter = Newsletter::find($this->newsletterId);
        $subscriber = NewsletterSubscriber::find($this->subscriberId);

        if (! $newsletter || ! $subscriber) {
            return;
        }

        $delivery = NewsletterDelivery::firstOrCreate(
            ['newsletter_id' => $newsletter->id, 'newsletter_subscriber_id' => $subscriber->id],
            ['status' => 'pending'],
        );

        if (in_array($delivery->status, ['sent', 'skipped'], true)) {
            return;
        }

        if ($subscriber->status() !== 'active') {
            $delivery->update(['status' => 'skipped', 'error' => 'Désinscrit avant l\'envoi.']);
            $newsletter->finalizeIfComplete();

            return;
        }

        try {
            Mail::to($subscriber->email)->send(new NewsletterMessage($newsletter, $subscriber));
        } catch (\Throwable $e) {
            // Dernier essai (ou file synchrone, sans nouvel essai) : on note l'échec et on passe au suivant ; sinon la file réessaie.
            if (config('queue.default') === 'sync' || $this->attempts() >= $this->tries) {
                $this->recordFailure($delivery, $newsletter, $e);

                return;
            }

            throw $e;
        }

        $delivery->update(['status' => 'sent', 'sent_at' => now(), 'error' => null]);
        $newsletter->finalizeIfComplete();
    }

    public function failed(\Throwable $e): void
    {
        $delivery = NewsletterDelivery::where(['newsletter_id' => $this->newsletterId, 'newsletter_subscriber_id' => $this->subscriberId])->first();
        $newsletter = Newsletter::find($this->newsletterId);

        if ($delivery && $newsletter && $delivery->status !== 'failed') {
            $this->recordFailure($delivery, $newsletter, $e);
        }
    }

    private function recordFailure(NewsletterDelivery $delivery, Newsletter $newsletter, \Throwable $e): void
    {
        report($e);
        $delivery->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)]);
        $newsletter->finalizeIfComplete();
    }
}
