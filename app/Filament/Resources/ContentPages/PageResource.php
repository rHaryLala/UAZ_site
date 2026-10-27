<?php

namespace App\Filament\Resources\ContentPages;

use App\Filament\Resources\ContentPages\Pages\CreatePage;
use App\Filament\Resources\ContentPages\Pages\EditPage;
use App\Filament\Resources\ContentPages\Pages\ListPages;
use App\Filament\Support\Layout;
use App\Filament\Support\MediaPicker;
use App\Filament\Support\PageBlocks;
use App\Filament\Support\PublicationSection;
use App\Filament\Support\PublicationTable;
use App\Filament\Support\SeoSection;
use App\Filament\Support\SlugField;
use App\Models\Page;
use BackedEnum;
use Closure;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|\UnitEnum|null $navigationGroup = 'Contenus';

    protected static ?string $modelLabel = 'page';

    protected static ?string $pluralModelLabel = 'pages';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Layout::mainAndSide(
                main: [
                    Section::make('Page')->schema([
                        TextInput::make('title')->label('Titre')->required()->maxLength(255),
                        Textarea::make('summary')->label('Introduction')->rows(2)->maxLength(500)
                            ->helperText('Court texte affiché sous le titre et utilisé pour le référencement.'),
                        MediaPicker::make('cover_id', 'Image d\'en-tête'),
                    ]),
                    PageBlocks::builder(),
                ],
                side: [
                    PublicationSection::make(),
                    Section::make('Adresse et position')->schema([
                        Select::make('parent_id')->label('Page parente')
                            ->options(fn (?Page $record) => Page::query()
                                ->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))
                                ->orderBy('path')->pluck('path', 'id'))
                            ->searchable()
                            ->placeholder('Aucune (page de premier niveau)')
                            ->live(),
                        SlugField::make(unique: false)
                            // Unicité par page parente : « licence » peut exister sous deux parents différents.
                            ->unique(
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('parent_id', $get('parent_id')),
                            )
                            ->rules([
                                fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get) {
                                    if (blank($get('parent_id')) && in_array($value, Page::RESERVED_SLUGS, true)) {
                                        $fail('Cette adresse est réservée par le site.');
                                    }
                                },
                            ]),
                        TextInput::make('sort_order')->label('Ordre')->numeric()->default(0)->minValue(0),
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
                TextColumn::make('title')->label('Titre')->searchable()->sortable()->weight('medium')
                    ->description(fn (Page $record) => '/'.$record->path),
                TextColumn::make('key')->label('Rôle')->badge()->color('info')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                PublicationTable::statusColumn(),
                PublicationTable::publishedAtColumn('Date'),
                TextColumn::make('updated_at')->label('Modifiée')->since()->sortable()->toggleable(),
            ])
            ->defaultSort('path')
            ->filters([PublicationTable::statusFilter()])
            ->recordActions(PublicationTable::recordActions())
            ->toolbarActions([PublicationTable::bulkActions()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPages::route('/'),
            'create' => CreatePage::route('/create'),
            'edit' => EditPage::route('/{record}/edit'),
        ];
    }
}
