<?php

namespace App\Policies;

use App\Models\Newsletter;
use App\Models\User;

/**
 * Lettres d'information : rédiger et envoyer un courrier à tous les abonnés est un acte de publication, réservé à ceux qui ont le droit
 * « content.publish » (éditeurs et super-administrateurs). Une lettre déjà envoyée n'est plus modifiable ni supprimable (elle fait foi).
 */
class NewsletterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('content.publish');
    }

    public function view(User $user, Newsletter $newsletter): bool
    {
        return $user->can('content.publish');
    }

    public function create(User $user): bool
    {
        return $user->can('content.publish');
    }

    public function update(User $user, Newsletter $newsletter): bool
    {
        return $newsletter->isDraft() && $user->can('content.publish');
    }

    public function delete(User $user, Newsletter $newsletter): bool
    {
        return $newsletter->isDraft() && $user->can('content.publish');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('content.publish');
    }
}
