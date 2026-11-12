<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un visuel du Studio UAZ : un modèle (écran d'accueil, direct, annonce…), un format, les textes saisis et, pour chaque emplacement photo,
 * la photo de la médiathèque avec son cadrage. Le dessin lui-même se fait dans le navigateur ; le serveur ne garde que les données.
 */
#[Fillable(['user_id', 'name', 'template', 'format', 'data', 'thumb'])]
class StudioDesign extends Model
{
    public const FORMATS = ['16:9', '1:1', '4:5', '9:16'];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
