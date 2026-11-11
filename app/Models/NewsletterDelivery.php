<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Envoi d'une lettre à un abonné : en attente, envoyé, échoué (avec la raison) ou ignoré (désinscrit entre-temps). */
#[Fillable(['newsletter_id', 'newsletter_subscriber_id', 'status', 'error', 'sent_at'])]
class NewsletterDelivery extends Model
{
    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    /** @return BelongsTo<Newsletter, $this> */
    public function newsletter(): BelongsTo
    {
        return $this->belongsTo(Newsletter::class);
    }

    /** @return BelongsTo<NewsletterSubscriber, $this> */
    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(NewsletterSubscriber::class, 'newsletter_subscriber_id');
    }
}
