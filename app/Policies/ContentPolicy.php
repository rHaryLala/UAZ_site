<?php

namespace App\Policies;

use App\Enums\PublicationStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Contenus éditoriaux avec statut (pages, actualités, événements, formations, albums).
 *
 * - éditeur : tout, y compris publier et supprimer ;
 * - contributeur : crée des contenus et modifie uniquement SES brouillons.
 */
class ContentPolicy
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
        return $user->can('content.create');
    }

    public function update(User $user, Model $record): bool
    {
        return $user->can('content.update') || $this->isOwnDraft($user, $record);
    }

    public function delete(User $user, Model $record): bool
    {
        return $user->can('content.delete');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('content.delete');
    }

    protected function isOwnDraft(User $user, Model $record): bool
    {
        return $user->can('content.create')
            && $record->getAttribute('user_id') === $user->getKey()
            && $record->getAttribute('status') === PublicationStatus::Draft;
    }
}
