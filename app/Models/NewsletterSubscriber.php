<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Abonné à la lettre d'information. Double confirmation : l'abonnement n'est actif qu'après le clic sur le lien reçu par e-mail. */
#[Fillable(['email', 'confirmation_sent_at', 'confirmed_at', 'unsubscribed_at'])]
class NewsletterSubscriber extends Model
{
    protected function casts(): array
    {
        return [
            'confirmation_sent_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }

    /** @param  Builder<static>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereNotNull('confirmed_at')->whereNull('unsubscribed_at');
    }

    /** @return 'active'|'pending'|'unsubscribed' */
    public function status(): string
    {
        return match (true) {
            $this->unsubscribed_at !== null => 'unsubscribed',
            $this->confirmed_at !== null => 'active',
            default => 'pending',
        };
    }
}
