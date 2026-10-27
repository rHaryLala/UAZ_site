<?php

namespace App\Filament\Resources\Events;

use App\Filament\Resources\Events\Pages\CreateEvent;
use App\Filament\Resources\Events\Pages\EditEvent;
use App\Filament\Resources\Events\Pages\ListEvents;
use App\Filament\Support\Layout;
use App\Filament\Support\MediaPicker;
use App\Filament\Support\PublicationSection;
use App\Filament\Support\PublicationTable;
use App\Filament\Support\Rich;
use App\Filament\Support\SeoSection;
use App\Filament\Support\SlugField;
use App\Models\Event;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EventResource extends Resource
{
    protected static ?string $model = Event::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'Contenus';

    protected static ?string $modelLabel = 'événement';

    protected static ?string $pluralModelLabel = 'événements';

    protected static ?string $navigationLabel = 'Événements';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Layout::mainAndSide(
                main: [
                    Section::make('Événement')->schema([
                        TextInput::make('title')->label('Titre')->required()->maxLength(255),
                        Textarea::make('summary')->label('Résumé')->rows(2)->maxLength(500),
                        MediaPicker::make('cover_id', 'Image'),
                        Rich::editor('description', 'Description'),
                    ]),
                    Section::make('Date et lieu')->columns(2)->schema([
                        DateTimePicker::make('starts_at')->label('Début')->seconds(false)->native(false)->required(),
                        DateTimePicker::make('ends_at')->label('Fin')->seconds(false)->native(false)->after('starts_at'),
                        Toggle::make('is_all_day')->label('Toute la journée')->columnSpanFull(),
                        TextInput::make('location')->label('Lieu')->maxLength(255),
                        TextInput::make('location_url')->label('Lien vers le plan (facultatif)')->url()->maxLength(500),
                        TextInput::make('registration_url')->label('Lien d\'inscription (facultatif)')->url()->maxLength(500)
                            ->columnSpanFull(),
                    ]),
                ],
                side: [
                    PublicationSection::make(),
                    Section::make('Adresse')->schema([SlugField::make()]),
                    SeoSection::make(),
                ],
            ),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // Chargement groupé des relations affichées dans la liste (évite une requête par ligne).
            ->modifyQueryUsing(fn ($query) => $query->with(['cover.media']))
            ->columns([
                PublicationTable::imageColumn(),
                TextColumn::make('title')->label('Titre')->searchable()->sortable()->limit(60)->weight('medium'),
                TextColumn::make('starts_at')->label('Début')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('location')->label('Lieu')->placeholder('—')->toggleable(),
                PublicationTable::statusColumn(),
            ])
            ->defaultSort('starts_at', 'desc')
            ->filters([
                PublicationTable::statusFilter(),
                SelectFilter::make('timing')->label('Période')
                    ->options(['upcoming' => 'À venir', 'past' => 'Passés'])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'upcoming' => $query->upcoming(),
                        'past' => $query->past(),
                        default => $query,
                    }),
            ])
            ->recordActions([
                PublicationTable::previewAction(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEvents::route('/'),
            'create' => CreateEvent::route('/create'),
            'edit' => EditEvent::route('/{record}/edit'),
        ];
    }
}
