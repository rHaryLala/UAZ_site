<?php

namespace App\Filament\Resources\Formations;

use App\Enums\FormationLevel;
use App\Filament\Resources\Formations\Pages\CreateFormation;
use App\Filament\Resources\Formations\Pages\EditFormation;
use App\Filament\Resources\Formations\Pages\ListFormations;
use App\Filament\Support\Layout;
use App\Filament\Support\MediaPicker;
use App\Filament\Support\PublicationSection;
use App\Filament\Support\PublicationTable;
use App\Filament\Support\Rich;
use App\Filament\Support\SeoSection;
use App\Filament\Support\SlugField;
use App\Models\Formation;
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
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class FormationResource extends Resource
{
    protected static ?string $model = Formation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|\UnitEnum|null $navigationGroup = 'Formations';

    protected static ?string $modelLabel = 'formation';

    protected static ?string $pluralModelLabel = 'formations';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Layout::mainAndSide(
                main: [
                    Tabs::make('Formation')->tabs([
                        Tab::make('Présentation')->schema([
                            TextInput::make('title')->label('Intitulé de la formation')->required()->maxLength(255),
                            Textarea::make('summary')->label('Résumé')->rows(3)->maxLength(500)
                                ->helperText('Affiché sur la carte de la formation.'),
                            Rich::editor('description', 'Description'),
                            MediaPicker::make('cover_id', 'Image'),
                        ]),
                        Tab::make('Débouchés')->schema([
                            Rich::editor('career_prospects', 'Débouchés professionnels'),
                        ]),
                        Tab::make('Admission')->schema([
                            Rich::editor('admission_requirements', 'Conditions d\'admission'),
                        ]),
                    ])->columnSpanFull(),
                ],
                side: [
                    PublicationSection::make(),
                    Section::make('Caractéristiques')->schema([
                        Select::make('faculty_id')->label('Faculté / département')
                            ->relationship('faculty', 'name')->searchable()->preload()->required(),
                        TextInput::make('mention')->label('Mention')->maxLength(255)
                            ->helperText('Ex. : Informatique, Droit, Sciences infirmières.'),
                        Select::make('level')->label('Niveau')->options(FormationLevel::class)
                            ->default(FormationLevel::Licence)->required()->selectablePlaceholder(false),
                        TextInput::make('duration')->label('Durée')->maxLength(100)->placeholder('3 ans (6 semestres)'),
                        TextInput::make('credits')->label('Crédits ECTS')->numeric()->minValue(0)->maxValue(1000),
                        Toggle::make('is_featured')->label('Mettre en avant sur l\'accueil'),
                        TextInput::make('sort_order')->label('Ordre')->numeric()->default(0)->minValue(0),
                        SlugField::make(),
                    ]),
                    SeoSection::make(),
                ],
            ),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                PublicationTable::imageColumn(),
                TextColumn::make('title')->label('Formation')->searchable()->sortable()->limit(60)->weight('medium'),
                TextColumn::make('level')->label('Niveau')->badge()->sortable(),
                TextColumn::make('faculty.name')->label('Faculté / département')->sortable()->toggleable(),
                TextColumn::make('mention')->label('Mention')->searchable()->toggleable(),
                ToggleColumn::make('is_featured')->label('À l\'accueil'),
                PublicationTable::statusColumn(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                PublicationTable::statusFilter(),
                SelectFilter::make('level')->label('Niveau')->options(FormationLevel::class),
                SelectFilter::make('faculty_id')->label('Faculté / département')->relationship('faculty', 'name'),
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
            'index' => ListFormations::route('/'),
            'create' => CreateFormation::route('/create'),
            'edit' => EditFormation::route('/{record}/edit'),
        ];
    }
}
