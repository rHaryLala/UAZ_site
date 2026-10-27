<?php

namespace App\Filament\Resources\Projects;

use App\Filament\Resources\Projects\Pages\ManageProjects;
use App\Filament\Support\MediaPicker;
use App\Filament\Support\Rich;
use App\Models\Project;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static string|\UnitEnum|null $navigationGroup = 'Contenus';

    protected static ?string $modelLabel = 'projet';

    protected static ?string $pluralModelLabel = 'projets à soutenir';

    protected static ?string $navigationLabel = 'Projets à soutenir';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->label('Titre du projet')->required()->maxLength(255)->placeholder('Ex. : Un grand dortoir pour les garçons')->columnSpanFull(),
            Textarea::make('summary')->label('Résumé')->rows(3)->maxLength(400)->columnSpanFull()
                ->helperText('Affiché sur la carte du projet (2 à 3 phrases).'),
            Rich::editor('body', 'Présentation détaillée')->columnSpanFull(),
            MediaPicker::make('cover_id', 'Image'),
            TextInput::make('location')->label('Lieu')->maxLength(255),
            TextInput::make('budget')->label('Budget prévu')->maxLength(120)->placeholder('ex. 100 000 €')
                ->helperText('Uniquement un chiffre officiel et vérifié.'),
            TextInput::make('status_label')->label('Avancement')->maxLength(80)->placeholder('ex. En construction'),
            TextInput::make('url')->label('Lien « En savoir plus »')->url()->maxLength(500),
            TextInput::make('sort_order')->label('Ordre d\'affichage')->numeric()->default(0)->minValue(0)
                ->helperText('Les nombres les plus petits s\'affichent en premier.'),
            Toggle::make('is_active')->label('Affiché sur le site')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            // Chargement groupé des relations affichées dans la liste (évite une requête par ligne).
            ->modifyQueryUsing(fn ($query) => $query->with(['cover.media']))
            ->columns([
                ImageColumn::make('cover_thumb')->label('Image')->size(44)
                    ->state(fn (Project $record) => $record->cover?->absoluteUrl('thumb')),
                TextColumn::make('title')->label('Projet')->searchable()->sortable()->weight('medium'),
                TextColumn::make('status_label')->label('Avancement'),
                TextColumn::make('budget')->label('Budget'),
                ToggleColumn::make('is_active')->label('Affiché'),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageProjects::route('/')];
    }
}
