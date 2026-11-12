<?php

use App\Models\NewsletterSubscriber;
use Illuminate\Database\Migrations\Migration;

/**
 * L'abonnement à la newsletter est immédiat (plus de confirmation par e-mail) : une adresse restée « en attente de confirmation » n'a plus rien à attendre,
 * elle devient abonnée (date d'abonnement = date de la demande). Les personnes désinscrites ne sont jamais touchées.
 */
return new class extends Migration
{
    public function up(): void
    {
        NewsletterSubscriber::query()->whereNull('confirmed_at')->whereNull('unsubscribed_at')->get()
            ->each(fn (NewsletterSubscriber $subscriber) => $subscriber->forceFill(['confirmed_at' => $subscriber->created_at ?? now()])->save());
    }

    public function down(): void
    {
        // Les abonnements ne sont pas annulés.
    }
};
