<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['source', 'source_id', 'model_type', 'model_id', 'hash', 'source_url', 'synced_at'])]
class ImportRecord extends Model
{
    protected function casts(): array
    {
        return ['synced_at' => 'datetime'];
    }

    public function model(): MorphTo
    {
        return $this->morphTo('model', 'model_type', 'model_id');
    }

    public static function lookup(string $source, int|string $sourceId): ?self
    {
        return static::query()->where('source', $source)->where('source_id', (string) $sourceId)->first();
    }

    /** Enregistre (ou met à jour) la correspondance entre un contenu source et son modèle local. */
    public static function remember(string $source, int|string $sourceId, Model $model, ?string $hash = null, ?string $url = null): self
    {
        return static::updateOrCreate(
            ['source' => $source, 'source_id' => (string) $sourceId],
            ['model_type' => $model::class, 'model_id' => $model->getKey(), 'hash' => $hash, 'source_url' => $url, 'synced_at' => now()],
        );
    }

    /** Le contenu local a-t-il été modifié (dans le back-office) depuis la dernière synchronisation ? */
    public function editedLocally(Model $model): bool
    {
        return $this->synced_at !== null
            && $model->getAttribute('updated_at') !== null
            && $model->getAttribute('updated_at')->gt($this->synced_at->copy()->addSeconds(2));
    }
}
