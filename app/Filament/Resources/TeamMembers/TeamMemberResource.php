<?php

namespace App\Filament\Resources\TeamMembers;

use App\Enums\TeamCategory;
use App\Filament\Resources\TeamMembers\Pages\ManageTeamMembers;
use App\Filament\Support\MediaPicker;
use App\Filament\Support\TranslationSection;
use App\Models\TeamMember;
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
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TeamMemberResource extends Resource
{
    protected static ?string $model = TeamMember::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|\UnitEnum|null $navigationGroup = 'Personnes & messages';

    protected static ?string $modelLabel = 'membre de l\'équipe';

    protected static ?string $pluralModelLabel = 'équipe';

    protected static ?string $navigationLabel = 'Équipe';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nom complet')->required()->maxLength(255),
            TextInput::make('job_title')->label('Fonction')->maxLength(255),
            Select::make('category')->label('Catégorie')->options(TeamCategory::class)
                ->default(TeamCategory::Administration)->required()->selectablePlaceholder(false),
            Select::make('faculty_id')->label('Faculté / département')
                ->relationship('faculty', 'name')->searchable()->preload(),
            Textarea::make('quote')->label('Citation')->rows(2)->maxLength(400)->columnSpanFull()
                ->helperText('Une phrase de la personne, affichée en grand quand on ouvre sa carte sur la page Équipe.'),
            Textarea::make('bio')->label('Message aux lecteurs')->rows(4)->maxLength(1200)->columnSpanFull()
                ->helperText('Ce que la personne souhaite partager (parcours, mot d\'accueil…). Texte validé par l\'intéressé(e).'),
            TextInput::make('email')->label('E-mail')->email()->maxLength(255),
            TextInput::make('phone')->label('Téléphone')->tel()->maxLength(50),
            MediaPicker::make('photo_id', 'Photo'),
            TextInput::make('sort_order')->label('Ordre d\'affichage')->numeric()->default(0)->minValue(0)
                ->helperText('Les nombres les plus petits s\'affichent en premier.'),
            Toggle::make('is_active')->label('Affiché sur le site')->default(true),
            TranslationSection::make(['job_title' => ['text', 'Fonction'], 'quote' => ['textarea', 'Citation'], 'bio' => ['textarea', 'Message aux lecteurs']]),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            // Chargement groupé des relations affichées dans la liste (évite une requête par ligne).
            ->modifyQueryUsing(fn ($query) => $query->with(['photo.media', 'faculty']))
            ->columns([
                ImageColumn::make('photo_thumb')->label('Photo')->circular()->size(44)
                    ->state(fn (TeamMember $record) => $record->photo?->absoluteUrl('thumb')),
                TextColumn::make('name')->label('Nom')->searchable()->sortable()->weight('medium'),
                TextColumn::make('job_title')->label('Fonction')->searchable(),
                TextColumn::make('category')->label('Catégorie')->badge(),
                TextColumn::make('faculty.name')->label('Département')->toggleable(),
                ToggleColumn::make('is_active')->label('Affiché'),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                SelectFilter::make('category')->label('Catégorie')->options(TeamCategory::class),
                SelectFilter::make('faculty_id')->label('Département')->relationship('faculty', 'name'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageTeamMembers::route('/')];
    }
}
