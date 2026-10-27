<?php

namespace App\Filament\Resources\ContactMessages;

use App\Filament\Resources\ContactMessages\Pages\ListContactMessages;
use App\Filament\Resources\ContactMessages\Pages\ViewContactMessage;
use App\Models\ContactMessage;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Messages reçus par le formulaire de contact : lecture seule (pas de création ni de modification). */
class ContactMessageResource extends Resource
{
    protected static ?string $model = ContactMessage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string|\UnitEnum|null $navigationGroup = 'Communauté';

    protected static ?string $modelLabel = 'message';

    protected static ?string $pluralModelLabel = 'messages de contact';

    protected static ?string $navigationLabel = 'Messages de contact';

    protected static ?string $recordTitleAttribute = 'subject';

    protected static ?int $navigationSort = 4;

    public static function getNavigationBadge(): ?string
    {
        $unread = \App\Support\AdminStats::unreadMessages();

        return $unread > 0 ? (string) $unread : null;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Message')->columns(2)->schema([
                TextEntry::make('name')->label('Nom'),
                TextEntry::make('email')->label('E-mail')->copyable(),
                TextEntry::make('phone')->label('Téléphone')->placeholder('—'),
                TextEntry::make('created_at')->label('Reçu le')->dateTime('d/m/Y à H:i'),
                TextEntry::make('subject')->label('Sujet')->columnSpanFull(),
                TextEntry::make('message')->label('Message')->columnSpanFull()->prose(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                IconColumn::make('read_at')->label('')->icon(fn ($state) => $state ? 'heroicon-o-envelope-open' : 'heroicon-s-envelope')
                    ->color(fn ($state) => $state ? 'gray' : 'primary')->tooltip(fn ($record) => $record->read_at ? 'Lu' : 'Non lu'),
                TextColumn::make('name')->label('Nom')->searchable()->weight('medium'),
                TextColumn::make('email')->label('E-mail')->searchable(),
                TextColumn::make('subject')->label('Sujet')->searchable()->limit(50),
                TextColumn::make('created_at')->label('Reçu le')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('unread')->label('Lecture')
                    ->trueLabel('Non lus')->falseLabel('Lus')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNull('read_at'),
                        false: fn (Builder $q) => $q->whereNotNull('read_at'),
                        blank: fn (Builder $q) => $q,
                    ),
            ])
            ->recordActions([ViewAction::make()->label('Lire')])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->emptyStateHeading('Aucun message pour le moment');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContactMessages::route('/'),
            'view' => ViewContactMessage::route('/{record}'),
        ];
    }
}
