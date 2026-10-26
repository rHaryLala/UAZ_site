<?php

namespace App\Models\Concerns;

use App\Enums\PublicationStatus;
use App\Support\Preview;
use Illuminate\Database\Eloquent\Builder;

/**
 * Statut brouillon/publié + publication planifiée.
 *
 * Un contenu est visible s'il est « publié » et que sa date de publication est vide ou passée.
 * Un contenu « publié » avec une date future est donc « programmé ».
 */
trait Publishable
{
    protected function initializePublishable(): void
    {
        $this->mergeCasts([
            'status' => PublicationStatus::class,
            'published_at' => 'datetime',
        ]);
    }

    protected static function bootPublishable(): void
    {
        static::creating(function (self $model): void {
            if ($model->isFillable('user_id') && $model->user_id === null && auth()->id()) {
                $model->user_id = auth()->id();
            }
        });

        static::saving(function (self $model): void {
            // Garde-fou serveur : seul un compte autorisé à publier peut passer un contenu en « publié ».
            $user = auth()->user();

            if ($user && $model->status === PublicationStatus::Published && $user->cannot('content.publish')) {
                $model->status = PublicationStatus::Draft;
            }

            if ($model->status === PublicationStatus::Published && $model->published_at === null) {
                $model->published_at = now();
            }
        });
    }

    /** @param  Builder<static>  $query */
    public function scopePublished(Builder $query): void
    {
        $query->where($query->qualifyColumn('status'), PublicationStatus::Published->value)
            ->where(function (Builder $q) use ($query) {
                $q->whereNull($query->qualifyColumn('published_at'))
                    ->orWhere($query->qualifyColumn('published_at'), '<=', now());
            });
    }

    /**
     * Contenus visibles sur le site public : publiés, ou tous en mode aperçu (compte du back-office).
     *
     * @param  Builder<static>  $query
     */
    public function scopeVisible(Builder $query): void
    {
        if (! Preview::active()) {
            $this->scopePublished($query);
        }
    }

    public function isLive(): bool
    {
        return $this->status === PublicationStatus::Published
            && ($this->published_at === null || $this->published_at->isPast());
    }

    public function isScheduled(): bool
    {
        return $this->status === PublicationStatus::Published
            && $this->published_at !== null
            && $this->published_at->isFuture();
    }

    /** Libellé affiché dans les tableaux : Brouillon, Programmé ou Publié. */
    public function publicationLabel(): string
    {
        return match (true) {
            $this->isScheduled() => 'Programmé',
            $this->isLive() => 'Publié',
            default => 'Brouillon',
        };
    }
}
