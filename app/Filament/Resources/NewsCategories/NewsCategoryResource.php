<?php

namespace App\Filament\Resources\NewsCategories;

use App\Filament\Resources\NewsCategories\Pages\ManageNewsCategories;
use App\Filament\Support\RowActions;
use App\Filament\Support\SlugField;
use App\Filament\Support\TranslationSection;
use App\Models\NewsCategory;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class NewsCategoryResource extends Resource
{
    protected static ?string $model = NewsCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|\UnitEnum|null $navigationGroup = 'Contenus';

    protected static ?string $modelLabel = 'catégorie d\'actualités';

    protected static ?string $pluralModelLabel = 'catégories d\'actualités';

    protected static ?string $navigationLabel = 'Catégories d\'actualités';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nom')->required()->maxLength(120),
            SlugField::make(),
            TranslationSection::make(['name' => ['text', 'Nom']]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nom')->searchable()->sortable()->weight('medium'),
                TextColumn::make('posts_count')->label('Actualités')->counts('posts'),
            ])
            ->defaultSort('name')
            ->recordActions(RowActions::editDelete())
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageNewsCategories::route('/')];
    }
}
