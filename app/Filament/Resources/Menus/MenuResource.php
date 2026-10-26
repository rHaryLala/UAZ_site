<?php

namespace App\Filament\Resources\Menus;

use App\Filament\Resources\Menus\Pages\CreateMenu;
use App\Filament\Resources\Menus\Pages\EditMenu;
use App\Filament\Resources\Menus\Pages\ListMenus;
use App\Models\Menu;
use App\Models\Page;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MenuResource extends Resource
{
    protected static ?string $model = Menu::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBars3;

    protected static string|\UnitEnum|null $navigationGroup = 'Site';

    protected static ?string $modelLabel = 'menu';

    protected static ?string $pluralModelLabel = 'menus de navigation';

    protected static ?string $navigationLabel = 'Menus';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 2;

    public const HANDLES = ['header' => 'En-tête (menu principal)', 'footer' => 'Pied de page'];

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nom')->required()->maxLength(120),
            Select::make('handle')->label('Emplacement')->options(self::HANDLES)->required()
                ->unique(ignoreRecord: true)->selectablePlaceholder(false),
            Repeater::make('items')
                ->label('Liens du menu')
                ->schema(self::itemFields(withChildren: true))
                ->addActionLabel('Ajouter un lien')
                ->reorderableWithButtons()
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                ->columnSpanFull(),
        ])->columns(2);
    }

    /** @return list<mixed> */
    private static function itemFields(bool $withChildren): array
    {
        $fields = [
            TextInput::make('label')->label('Libellé')->required()->maxLength(80),
            Select::make('type')->label('Type de lien')
                ->options(['page' => 'Page du site', 'url' => 'Adresse libre'])
                ->default('page')->live()->required()->selectablePlaceholder(false),
            Select::make('page_id')->label('Page')
                ->options(fn () => Page::query()->orderBy('path')->pluck('path', 'id')->map(fn ($p) => '/'.$p)->all())
                ->searchable()
                ->visible(fn (Get $get) => $get('type') === 'page')
                ->required(fn (Get $get) => $get('type') === 'page'),
            TextInput::make('url')->label('Adresse')
                ->helperText('Chemin du site (/contact) ou adresse complète (https://…).')
                ->maxLength(500)
                ->visible(fn (Get $get) => $get('type') === 'url')
                ->required(fn (Get $get) => $get('type') === 'url'),
            Toggle::make('new_tab')->label('Ouvrir dans un nouvel onglet')->default(false),
        ];

        if ($withChildren) {
            $fields[] = Repeater::make('children')
                ->label('Sous-menu')
                ->schema(self::itemFields(withChildren: false))
                ->addActionLabel('Ajouter un sous-lien')
                ->reorderableWithButtons()
                ->collapsible()
                ->collapsed()
                ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                ->columnSpanFull();
        }

        return $fields;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nom')->weight('medium'),
                TextColumn::make('handle')->label('Emplacement')->badge()
                    ->formatStateUsing(fn (string $state) => self::HANDLES[$state] ?? $state),
                TextColumn::make('items_count')->label('Liens')
                    ->state(fn (Menu $record) => count($record->items ?? [])),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMenus::route('/'),
            'create' => CreateMenu::route('/create'),
            'edit' => EditMenu::route('/{record}/edit'),
        ];
    }
}
