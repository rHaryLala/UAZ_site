<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Données de référence sans cycle de brouillon (facultés, équipe, témoignages, partenaires, catégories) :
 * lecture pour tous les rôles de contenu, écriture réservée à ceux qui peuvent modifier le contenu.
 */
class ManagedContentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('content.view');
    }

    public function view(User $user, Model $record): bool
    {
        return $user->can('content.view');
    }

    public function create(User $user): bool
    {
        return $user->can('content.update');
    }

    public function update(User $user, Model $record): bool
    {
        return $user->can('content.update');
    }

    public function delete(User $user, Model $record): bool
    {
        return $user->can('content.delete');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('content.delete');
    }
}
