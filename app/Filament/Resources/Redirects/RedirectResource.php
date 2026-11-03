<?php

namespace App\Filament\Resources\Redirects;

use App\Filament\Resources\Redirects\Pages\ManageRedirects;
use App\Filament\Support\RowActions;
use App\Models\Redirect;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class RedirectResource extends Resource
{
    protected static ?string $model = Redirect::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnRight;

    protected static string|\UnitEnum|null $navigationGroup = 'Site & médias';

    protected static ?string $modelLabel = 'redirection';

    protected static ?string $pluralModelLabel = 'redirections';

    protected static ?string $recordTitleAttribute = 'from_path';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('from_path')->label('Ancienne adresse')->required()->maxLength(255)
                ->placeholder('/ancienne-page/')
                ->helperText('Chemin sur le site (ex. /blog/) — le domaine est facultatif.')
                ->unique(ignoreRecord: true)
                ->dehydrateStateUsing(fn (string $state) => Redirect::normalize($state)),
            TextInput::make('to_path')->label('Nouvelle adresse')->required()->maxLength(500)
                ->placeholder('/actualites')
                ->helperText('Chemin du site (/actualites) ou adresse complète (https://…).'),
            Select::make('status_code')->label('Type')
                ->options([301 => '301 — Permanente (recommandé)', 302 => '302 — Temporaire'])
                ->default(301)->required()->selectablePlaceholder(false),
            Toggle::make('is_active')->label('Active')->default(true),
            TextInput::make('note')->label('Note interne')->maxLength(255)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('from_path')->label('Ancienne adresse')->searchable()->sortable()->copyable(),
                TextColumn::make('to_path')->label('Nouvelle adresse')->searchable()->limit(50),
                TextColumn::make('status_code')->label('Code')->badge(),
                TextColumn::make('hits')->label('Visites')->numeric()->sortable(),
                TextColumn::make('last_hit_at')->label('Dernière utilisation')->since()->placeholder('Jamais')->toggleable(),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->defaultSort('from_path')
            ->filters([TernaryFilter::make('is_active')->label('Active')])
            ->recordActions(RowActions::editDelete())
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageRedirects::route('/')];
    }
}
