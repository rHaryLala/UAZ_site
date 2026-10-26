<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Comme les autres contenus, mais les pages « système » (accueil, admissions…) ne peuvent pas être supprimées. */
class PagePolicy extends ContentPolicy
{
    public function delete(User $user, Model $record): bool
    {
        return blank($record->getAttribute('key')) && parent::delete($user, $record);
    }
}
