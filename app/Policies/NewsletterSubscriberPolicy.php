<?php

namespace App\Policies;

use App\Models\NewsletterSubscriber;
use App\Models\User;

/** Abonnés à la newsletter : lecture seule dans l'admin (mêmes droits que les messages de contact) ; suppression possible. */
class NewsletterSubscriberPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('messages.view');
    }

    public function view(User $user, NewsletterSubscriber $subscriber): bool
    {
        return $user->can('messages.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, NewsletterSubscriber $subscriber): bool
    {
        return false;
    }

    public function delete(User $user, NewsletterSubscriber $subscriber): bool
    {
        return $user->can('messages.delete');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('messages.delete');
    }
}
