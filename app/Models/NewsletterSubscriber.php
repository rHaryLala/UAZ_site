<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Abonné à la newsletter. Actif = « confirmed_at » renseigné et pas de « unsubscribed_at » : l'abonnement depuis le site est immédiat (aucun e-mail de
 * confirmation). Une adresse sans « confirmed_at » (reprise d'un export MailPoet « non confirmé ») ne reçoit rien tant que la personne ne s'abonne pas elle-même.
 * « confirmation_sent_at » n'est plus renseigné : colonne conservée, sans usage.
 */
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
