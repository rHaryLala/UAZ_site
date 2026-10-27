<?php

namespace App\Filament\Support;

use App\Enums\PublicationStatus;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/** Colonnes, filtre et actions communs aux contenus avec brouillon / publication planifiée. */
class PublicationTable
{
    public static function statusColumn(): TextColumn
    {
        return TextColumn::make('status')
            ->label('Statut')
            ->badge()
            ->state(fn (Model $record): string => $record->publicationLabel())
            ->color(fn (string $state): string => match ($state) {
                'Publié' => 'success',
                'Programmé' => 'warning',
                default => 'gray',
            })
            ->sortable();
    }

    public static function publishedAtColumn(string $label = 'Publié le'): TextColumn
    {
        return TextColumn::make('published_at')->label($label)->dateTime('d/m/Y H:i')->sortable()->placeholder('—');
    }

    public static function imageColumn(string $relation = 'cover', string $label = 'Image'): ImageColumn
    {
        return ImageColumn::make($relation.'_thumb')
            ->label($label)
            ->state(fn (Model $record): ?string => $record->{$relation}?->absoluteUrl('thumb'))
            ->square()
            ->size(48);
    }

    public static function statusFilter(): SelectFilter
    {
        return SelectFilter::make('publication')
            ->label('Statut')
            ->options(['draft' => 'Brouillons', 'scheduled' => 'Programmés', 'published' => 'Publiés'])
            ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                'draft' => $query->where('status', PublicationStatus::Draft->value),
                'scheduled' => $query->where('status', PublicationStatus::Published->value)->where('published_at', '>', now()),
                'published' => $query->published(),
                default => $query,
            });
    }

    /** Ouvre la page publique avec le mode aperçu (visible des comptes connectés, même en brouillon). */
    public static function previewAction(): Action
    {
        return Action::make('preview')
            ->label('Aperçu')
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->url(fn (Model $record): string => $record->url().'?apercu=1')
            ->openUrlInNewTab();
    }

    private static function canPublish(): bool
    {
        return (bool) auth()->user()?->can('content.publish');
    }

    /** Publie tout de suite (une date de publication vide ou future est remplacée par maintenant). */
    public static function publishAction(): Action
    {
        return Action::make('publish')
            ->label('Publier')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (Model $record): bool => static::canPublish() && ($record->status !== PublicationStatus::Published || $record->published_at?->isFuture()))
            ->requiresConfirmation()
            ->modalHeading('Publier maintenant ?')
            ->modalDescription('Le contenu sera visible sur le site tout de suite.')
            ->action(function (Model $record): void {
                static::publish($record);
                Notification::make()->success()->title('Publié : le contenu est maintenant visible sur le site.')->send();
            });
    }

    public static function unpublishAction(): Action
    {
        return Action::make('unpublish')
            ->label('Repasser en brouillon')
            ->icon(Heroicon::OutlinedEyeSlash)
            ->color('warning')
            ->visible(fn (Model $record): bool => static::canPublish() && $record->status === PublicationStatus::Published)
            ->requiresConfirmation()
            ->modalHeading('Retirer du site ?')
            ->modalDescription('Le contenu ne sera plus visible du public. Il reste modifiable.')
            ->action(function (Model $record): void {
                $record->update(['status' => PublicationStatus::Draft]);
                Notification::make()->success()->title('Repassé en brouillon : le contenu n\'est plus visible sur le site.')->send();
            });
    }

    /**
     * Copie le contenu en brouillon, avec « (copie) » au titre et une nouvelle adresse.
     *
     * @param  list<string>  $excluded  attributs supplémentaires à ne pas copier
     */
    public static function replicateAction(string $titleField = 'title', array $excluded = []): ReplicateAction
    {
        return ReplicateAction::make()
            ->label('Dupliquer')
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->excludeAttributes(array_merge(['slug', 'key', 'path', 'published_at', 'user_id'], $excluded))
            ->beforeReplicaSaved(function (Model $replica) use ($titleField): void {
                $replica->{$titleField} = $replica->{$titleField}.' (copie)';
                $replica->status = PublicationStatus::Draft;
            })
            ->successNotificationTitle('Dupliqué : la copie est un brouillon, à modifier puis à publier.');
    }

    public static function publish(Model $record): void
    {
        $record->update([
            'status' => PublicationStatus::Published,
            'published_at' => $record->published_at && $record->published_at->isPast() ? $record->published_at : now(),
        ]);
    }

    /**
     * Actions de chaque ligne : aperçu et modification en évidence, le reste dans le menu « ⋯ ».
     *
     * @param  list<string>  $excluded
     * @return list<mixed>
     */
    public static function recordActions(string $titleField = 'title', array $excluded = []): array
    {
        return [
            static::previewAction(),
            EditAction::make(),
            ActionGroup::make([
                static::replicateAction($titleField, $excluded),
                static::publishAction(),
                static::unpublishAction(),
                DeleteAction::make(),
            ])->icon(Heroicon::OutlinedEllipsisVertical)->tooltip('Plus d\'actions'),
        ];
    }

    /** Actions sur la sélection : publier, repasser en brouillon, supprimer. */
    public static function bulkActions(): BulkActionGroup
    {
        return BulkActionGroup::make([
            BulkAction::make('publishSelected')
                ->label('Publier la sélection')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->visible(fn (): bool => static::canPublish())
                ->requiresConfirmation()
                ->modalHeading('Publier les éléments sélectionnés ?')
                ->action(function (Collection $records): void {
                    $records->each(fn (Model $record) => static::publish($record));
                    Notification::make()->success()->title($records->count().' élément(s) publié(s).')->send();
                })
                ->deselectRecordsAfterCompletion(),
            BulkAction::make('unpublishSelected')
                ->label('Repasser en brouillon')
                ->icon(Heroicon::OutlinedEyeSlash)
                ->visible(fn (): bool => static::canPublish())
                ->requiresConfirmation()
                ->modalHeading('Retirer les éléments sélectionnés du site ?')
                ->action(function (Collection $records): void {
                    $records->each(fn (Model $record) => $record->update(['status' => PublicationStatus::Draft]));
                    Notification::make()->success()->title($records->count().' élément(s) repassé(s) en brouillon.')->send();
                })
                ->deselectRecordsAfterCompletion(),
            DeleteBulkAction::make(),
        ]);
    }
}
