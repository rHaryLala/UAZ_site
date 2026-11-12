<?php

namespace App\Filament\Resources\NewsletterSubscribers;

use App\Filament\Resources\NewsletterSubscribers\Pages\ListNewsletterSubscribers;
use App\Models\NewsletterSubscriber;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Abonnés à la newsletter (fenêtre « S'abonner » du site) : lecture seule, export CSV, suppression sur demande (droit à l'effacement). */
class NewsletterSubscriberResource extends Resource
{
    protected static ?string $model = NewsletterSubscriber::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static string|\UnitEnum|null $navigationGroup = 'Personnes & messages';

    protected static ?string $modelLabel = 'abonné';

    protected static ?string $pluralModelLabel = 'abonnés à la newsletter';

    protected static ?string $navigationLabel = 'Abonnés à la newsletter';

    protected static ?string $recordTitleAttribute = 'email';

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('email')->label('E-mail')->searchable()->copyable()->weight('medium'),
                TextColumn::make('status')->label('Statut')
                    ->state(fn (NewsletterSubscriber $record) => $record->status())
                    ->badge()
                    ->formatStateUsing(fn (string $state) => self::statusLabels()[$state])
                    ->color(fn (string $state) => match ($state) {
                        'active' => 'success',
                        'pending' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')->label('Demande du')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('confirmed_at')->label('Confirmé le')->dateTime('d/m/Y H:i')->placeholder('—')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->label('Statut')->options(self::statusLabels())
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'active' => $query->active(),
                        'pending' => $query->whereNull('confirmed_at')->whereNull('unsubscribed_at'),
                        'unsubscribed' => $query->whereNotNull('unsubscribed_at'),
                        default => $query,
                    }),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->emptyStateHeading('Aucun abonné pour le moment');
    }

    /** @return array<string, string> */
    public static function statusLabels(): array
    {
        return ['active' => 'Abonné', 'pending' => 'Non confirmé (ne reçoit rien)', 'unsubscribed' => 'Désinscrit'];
    }

    public static function getPages(): array
    {
        return ['index' => ListNewsletterSubscribers::route('/')];
    }
}
