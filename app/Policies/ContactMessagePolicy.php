<?php

namespace App\Policies;

use App\Models\ContactMessage;
use App\Models\User;

/** Messages de contact : lecture seule dans l'admin ; suppression possible (droit à l'effacement). */
class ContactMessagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('messages.view');
    }

    public function view(User $user, ContactMessage $message): bool
    {
        return $user->can('messages.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ContactMessage $message): bool
    {
        return false;
    }

    public function delete(User $user, ContactMessage $message): bool
    {
        return $user->can('messages.delete');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('messages.delete');
    }
}
