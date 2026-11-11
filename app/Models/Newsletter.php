<?php

namespace App\Models;

use App\Jobs\SendNewsletter;
use App\Support\NewsletterBlocks;
use App\Support\NewsletterRenderer;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Lettre d'information composée dans l'éditeur visuel du back-office (plan de travail). Cycle : brouillon (modifiable) → en cours d'envoi →
 * envoyée (figée). Elle part à chaque abonné ACTIF (confirmé, non désinscrit) ; chaque envoi est tracé dans newsletter_deliveries.
 *
 * Contenu : une liste de blocs (App\Support\NewsletterBlocks) et un thème (couleurs, police) ; le courrier est dessiné par
 * App\Support\NewsletterRenderer, le même code pour l'aperçu de l'éditeur et pour l'envoi.
 */
#[Fillable(['subject', 'preheader', 'blocks', 'theme', 'status', 'sent_at', 'recipients_count', 'created_by'])]
class Newsletter extends Model
{
    public const DRAFT = 'draft';

    public const SENDING = 'sending';

    public const SENT = 'sent';

    protected static function booted(): void
    {
        // Une lettre envoyée fait foi : plus de modification de son contenu, ni de suppression, quels que soient les droits (le super-administrateur
        // contourne les politiques d'accès : la règle est donc aussi posée ici).
        static::updating(function (Newsletter $newsletter): void {
            if ($newsletter->getOriginal('status') !== self::DRAFT && $newsletter->isDirty(['subject', 'preheader', 'blocks', 'theme'])) {
                throw new \LogicException('Une lettre envoyée ne peut plus être modifiée.');
            }
        });

        static::deleting(function (Newsletter $newsletter): void {
            if (! $newsletter->isDraft()) {
                throw new \LogicException('Une lettre envoyée ne peut pas être supprimée.');
            }
        });
    }

    protected function casts(): array
    {
        return ['blocks' => 'array', 'theme' => 'array', 'sent_at' => 'datetime', 'recipients_count' => 'integer'];
    }

    /** Lettre neuve : un titre et un texte d'exemple pour ne pas démarrer d'une page blanche. */
    public static function startingBlocks(): array
    {
        return NewsletterBlocks::starter();
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<NewsletterDelivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(NewsletterDelivery::class);
    }

    public function isDraft(): bool
    {
        return $this->status === self::DRAFT;
    }

    /** L'état tel que l'éditeur et le dessinateur de courrier le manipulent. */
    public function state(): array
    {
        return [
            'subject' => $this->subject,
            'preheader' => $this->preheader ?? '',
            'blocks' => $this->blocks ?? [],
            'theme' => NewsletterBlocks::theme($this->theme),
        ];
    }

    /** Nombre d'abonnés qui recevraient la lettre maintenant. */
    public static function audienceCount(): int
    {
        return NewsletterSubscriber::active()->count();
    }

    /**
     * Lance l'envoi à tous les abonnés actifs. Un brouillon seulement, et seulement s'il y a des destinataires : on fige le nombre de
     * destinataires puis on confie l'envoi à la file d'attente (un travail par abonné, voir App\Jobs\SendNewsletter).
     *
     * @throws \LogicException
     */
    public function send(): void
    {
        DB::transaction(function (): void {
            $fresh = static::query()->lockForUpdate()->findOrFail($this->id);

            if (! $fresh->isDraft()) {
                throw new \LogicException('Cette lettre a déjà été envoyée.');
            }

            $count = self::audienceCount();

            if ($count === 0) {
                throw new \LogicException('Aucun abonné actif : rien à envoyer.');
            }

            $fresh->forceFill(['status' => self::SENDING, 'recipients_count' => $count])->save();
        });

        $this->refresh();
        SendNewsletter::dispatch($this->id);
    }

    /** Passe la lettre à « envoyée » quand chaque destinataire a été traité (envoyé, échoué ou ignoré). */
    public function finalizeIfComplete(): void
    {
        $this->refresh();

        if ($this->status !== self::SENDING) {
            return;
        }

        $done = $this->deliveries()->whereIn('status', ['sent', 'failed', 'skipped'])->count();

        if ($done >= $this->recipients_count) {
            $this->forceFill(['status' => self::SENT, 'sent_at' => now()])->save();
        }
    }

    public function sentCount(): int
    {
        return $this->deliveries()->where('status', 'sent')->count();
    }

    public function failedCount(): int
    {
        return $this->deliveries()->where('status', 'failed')->count();
    }

    /** Le courrier complet (HTML) pour un abonné, ou pour un envoi d'essai si $unsubscribeUrl est donné sans abonné. */
    public function html(?NewsletterSubscriber $subscriber = null, ?string $unsubscribeUrl = null): string
    {
        return NewsletterRenderer::html($this->state(), $subscriber, $unsubscribeUrl);
    }

    /** Version texte de la lettre pour les logiciels de messagerie qui n'affichent pas le HTML. */
    public function plainText(?string $unsubscribeUrl = null): string
    {
        return NewsletterRenderer::text($this->state(), $unsubscribeUrl);
    }
}
