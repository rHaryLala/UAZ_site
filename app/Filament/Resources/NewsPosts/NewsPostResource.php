<?php

namespace App\Filament\Resources\NewsPosts;

use App\Filament\Resources\NewsPosts\Pages\CreateNewsPost;
use App\Filament\Resources\NewsPosts\Pages\EditNewsPost;
use App\Filament\Resources\NewsPosts\Pages\ListNewsPosts;
use App\Filament\Support\Layout;
use App\Filament\Support\MediaPicker;
use App\Filament\Support\PublicationSection;
use App\Filament\Support\PublicationTable;
use App\Filament\Support\Rich;
use App\Filament\Support\SeoSection;
use App\Filament\Support\SlugField;
use App\Models\NewsPost;
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
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class NewsPostResource extends Resource
{
    protected static ?string $model = NewsPost::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static string|\UnitEnum|null $navigationGroup = 'Contenus';

    protected static ?string $modelLabel = 'actualité';

    protected static ?string $pluralModelLabel = 'actualités';

    protected static ?string $navigationLabel = 'Actualités';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 2;

    /** Pastille de navigation : nombre d'actualités en brouillon (à relire et à publier). */
    public static function getNavigationBadge(): ?string
    {
        $drafts = \App\Support\AdminStats::get()['news_drafts'];

        return $drafts > 0 ? (string) $drafts : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Actualités en brouillon';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Layout::mainAndSide(
                main: [
                    Section::make('Actualité')->schema([
                        TextInput::make('title')->label('Titre')->required()->maxLength(255)->placeholder('Ex. : Rentrée académique 2026-2027'),
                        Textarea::make('excerpt')->label('Extrait')->rows(3)->maxLength(500)
                            ->helperText('Court résumé affiché dans les listes et les partages.'),
                        MediaPicker::make('cover_id', 'Image à la une'),
                        Rich::editor('body', 'Texte'),
                    ]),
                ],
                side: [
                    PublicationSection::make(),
                    Section::make('Classement')->schema([
                        Select::make('news_category_id')->label('Catégorie')
                            ->relationship('category', 'name')
                            ->searchable()->preload()
                            ->createOptionForm([TextInput::make('name')->label('Nom')->required()->maxLength(120)]),
                        Select::make('author_id')->label('Auteur')
                            ->relationship('author', 'name')
                            ->default(fn () => auth()->id())
                            ->searchable()->preload(),
                        Toggle::make('is_featured')->label('Mettre à la une')
                            ->helperText('Affichée en premier sur la page d\'accueil.'),
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
            // Chargement groupé des relations affichées dans la liste (évite une requête par ligne).
            ->modifyQueryUsing(fn ($query) => $query->with(['cover.media', 'category', 'author']))
            ->columns([
                PublicationTable::imageColumn(),
                TextColumn::make('title')->label('Titre')->searchable()->sortable()->limit(60)->weight('medium'),
                TextColumn::make('category.name')->label('Catégorie')->badge()->color('gray')->placeholder('—')->toggleable(),
                TextColumn::make('author.name')->label('Auteur')->toggleable(),
                PublicationTable::statusColumn(),
                PublicationTable::publishedAtColumn(),
            ])
            ->defaultSort('published_at', 'desc')
            ->filters([
                PublicationTable::statusFilter(),
                SelectFilter::make('news_category_id')->label('Catégorie')->relationship('category', 'name'),
                TernaryFilter::make('is_featured')->label('À la une'),
            ])
            ->recordActions(PublicationTable::recordActions())
            ->toolbarActions([PublicationTable::bulkActions()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNewsPosts::route('/'),
            'create' => CreateNewsPost::route('/create'),
            'edit' => EditNewsPost::route('/{record}/edit'),
        ];
    }
}
