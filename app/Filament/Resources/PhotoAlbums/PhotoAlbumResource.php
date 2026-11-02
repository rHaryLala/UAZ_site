<?php

namespace App\Filament\Resources\PhotoAlbums;

use App\Filament\Resources\PhotoAlbums\Pages\CreatePhotoAlbum;
use App\Filament\Resources\PhotoAlbums\Pages\EditPhotoAlbum;
use App\Filament\Resources\PhotoAlbums\Pages\ListPhotoAlbums;
use App\Filament\Support\Layout;
use App\Filament\Support\TranslationSection;
use App\Filament\Support\MediaPicker;
use App\Filament\Support\PublicationSection;
use App\Filament\Support\PublicationTable;
use App\Filament\Support\SlugField;
use App\Models\MediaAsset;
use App\Models\PhotoAlbum;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PhotoAlbumResource extends Resource
{
    protected static ?string $model = PhotoAlbum::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Contenus';

    protected static ?string $modelLabel = 'album photo';

    protected static ?string $pluralModelLabel = 'albums photo';

    protected static ?string $navigationLabel = 'Albums photo';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Layout::mainAndSide(
                main: [
                    Section::make('Album')->schema([
                        TextInput::make('title')->label('Titre')->required()->maxLength(255)->placeholder('Ex. : Remise des diplômes 2026'),
                        Textarea::make('description')->label('Description')->rows(2)->maxLength(500),
                        MediaPicker::make('cover_id', 'Image de couverture'),
                    ]),
                    Section::make('Photos')->schema([
                        MediaPicker::make('quick_add', 'Ajouter plusieurs photos d\'un coup')
                            ->multiple()
                            ->dehydrated(false)
                            ->live()
                            ->afterStateUpdated(function (?array $state, Get $get, Set $set): void {
                                // On écarte la ligne vide proposée par défaut avant d'ajouter les photos choisies.
                                $photos = collect($get('photos') ?? [])->filter(fn ($photo) => filled($photo['media_asset_id'] ?? null))->all();
                                foreach ($state ?? [] as $id) {
                                    $photos[(string) Str::uuid()] = ['media_asset_id' => $id];
                                }
                                $set('photos', $photos);
                                $set('quick_add', []);
                            }),
                        Repeater::make('photos')
                            ->label('Photos de l\'album')
                            ->schema([MediaPicker::make('media_asset_id', 'Photo')->required()])
                            ->addActionLabel('Ajouter une photo')
                            ->reorderableWithButtons()
                            ->collapsible()
                            ->collapsed()
                            ->itemLabel(fn (array $state): ?string => filled($state['media_asset_id'] ?? null)
                                ? MediaAsset::find($state['media_asset_id'])?->label() : null),
                    ]),
                    TranslationSection::make(['title' => ['text', 'Titre'], 'description' => ['textarea', 'Description']]),
                ],
                side: [
                    PublicationSection::make('Date de mise en ligne'),
                    Section::make('Détails')->schema([
                        DatePicker::make('taken_on')->label('Date de l\'événement')->native(false),
                        SlugField::make(),
                    ]),
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
                TextColumn::make('title')->label('Titre')->searchable()->sortable()->weight('medium'),
                TextColumn::make('photos_count')->label('Photos')->counts('photos'),
                TextColumn::make('taken_on')->label('Date')->date('d/m/Y')->sortable()->placeholder('—'),
                PublicationTable::statusColumn(),
            ])
            ->defaultSort('taken_on', 'desc')
            ->filters([PublicationTable::statusFilter()])
            ->recordActions(PublicationTable::recordActions())
            ->toolbarActions([PublicationTable::bulkActions()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPhotoAlbums::route('/'),
            'create' => CreatePhotoAlbum::route('/create'),
            'edit' => EditPhotoAlbum::route('/{record}/edit'),
        ];
    }
}
