<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Menus et redirections : réservés aux comptes ayant la permission « settings.manage ». */
class SettingsPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('settings.manage');
    }

    public function view(User $user, Model $record): bool
    {
        return $user->can('settings.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('settings.manage');
    }

    public function update(User $user, Model $record): bool
    {
        return $user->can('settings.manage');
    }

    public function delete(User $user, Model $record): bool
    {
        return $user->can('settings.manage');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('settings.manage');
    }
}
