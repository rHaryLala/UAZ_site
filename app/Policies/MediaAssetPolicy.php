<?php

namespace App\Policies;

use App\Models\MediaAsset;
use App\Models\User;

class MediaAssetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('media.view') || $user->can('media.manage');
    }

    public function view(User $user, MediaAsset $asset): bool
    {
        return $this->viewAny($user);
    }

    /** Les contributeurs peuvent envoyer des images pour illustrer leurs brouillons. */
    public function create(User $user): bool
    {
        return $user->can('media.create') || $user->can('media.manage');
    }

    public function update(User $user, MediaAsset $asset): bool
    {
        return $user->can('media.manage');
    }

    public function delete(User $user, MediaAsset $asset): bool
    {
        return $user->can('media.manage');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('media.manage');
    }
}
