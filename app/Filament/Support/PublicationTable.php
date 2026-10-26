<?php

namespace App\Filament\Support;

use App\Enums\PublicationStatus;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Colonnes, filtre et action communs aux contenus avec brouillon / publication planifiée. */
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
            ->state(fn (Model $record): ?string => $record->{$relation}?->url('thumb'))
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
}
