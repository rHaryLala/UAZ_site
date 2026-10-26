<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.manage');
    }

    public function view(User $user, User $record): bool
    {
        return $user->can('users.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('users.manage');
    }

    public function update(User $user, User $record): bool
    {
        return $user->can('users.manage');
    }

    /** On ne peut pas supprimer son propre compte. */
    public function delete(User $user, User $record): bool
    {
        return $user->can('users.manage') && $user->isNot($record);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('users.manage');
    }
}
