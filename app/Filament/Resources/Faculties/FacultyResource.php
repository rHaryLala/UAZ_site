<?php

namespace App\Filament\Resources\Faculties;

use App\Enums\FacultyType;
use App\Filament\Resources\Faculties\Pages\ManageFaculties;
use App\Filament\Support\MediaPicker;
use App\Filament\Support\PublicationTable;
use App\Filament\Support\Rich;
use App\Filament\Support\SlugField;
use App\Models\Faculty;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class FacultyResource extends Resource
{
    protected static ?string $model = Faculty::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|\UnitEnum|null $navigationGroup = 'Formations';

    protected static ?string $modelLabel = 'faculté / département';

    protected static ?string $pluralModelLabel = 'facultés et départements';

    protected static ?string $navigationLabel = 'Facultés / départements';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nom')->required()->maxLength(255),
            Select::make('type')->label('Type')->options(FacultyType::class)
                ->default(FacultyType::Department)->required()->selectablePlaceholder(false),
            Textarea::make('summary')->label('Résumé')->rows(2)->maxLength(500)->columnSpanFull(),
            Rich::editor('description', 'Présentation'),
            MediaPicker::make('cover_id', 'Image'),
            TextInput::make('sort_order')->label('Ordre')->numeric()->default(0)->minValue(0),
            SlugField::make(),
            Toggle::make('is_active')->label('Affichée sur le site')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            // Chargement groupé des relations affichées dans la liste (évite une requête par ligne).
            ->modifyQueryUsing(fn ($query) => $query->with(['cover.media']))
            ->columns([
                PublicationTable::imageColumn(),
                TextColumn::make('name')->label('Nom')->searchable()->sortable()->weight('medium'),
                TextColumn::make('type')->label('Type')->badge(),
                TextColumn::make('formations_count')->label('Formations')->counts('formations'),
                ToggleColumn::make('is_active')->label('Affichée'),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageFaculties::route('/')];
    }
}
