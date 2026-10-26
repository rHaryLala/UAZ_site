<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static ?string $modelLabel = 'utilisateur';

    protected static ?string $pluralModelLabel = 'utilisateurs';

    protected static ?string $recordTitleAttribute = 'name';

    public const ROLE_LABELS = [
        'super-admin' => 'Super-administrateur',
        'editor' => 'Éditeur',
        'contributor' => 'Contributeur',
    ];

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nom')->required()->maxLength(255),
            TextInput::make('email')->label('Adresse e-mail')->email()->required()->maxLength(255)->unique(ignoreRecord: true),
            TextInput::make('password')->label('Mot de passe')->password()->revealable()
                ->required(fn (string $operation) => $operation === 'create')
                ->minLength(10)
                ->dehydrated(fn (?string $state) => filled($state))
                ->dehydrateStateUsing(fn (string $state) => Hash::make($state))
                ->helperText(fn (string $operation) => $operation === 'edit' ? 'Laissez vide pour conserver le mot de passe actuel.' : '10 caractères minimum.'),
            Select::make('roles')->label('Rôle')
                ->relationship('roles', 'name')
                ->getOptionLabelFromRecordUsing(fn ($record) => self::ROLE_LABELS[$record->name] ?? $record->name)
                ->preload()->required()
                ->helperText('Éditeur : publie et gère les contenus et médias. Contributeur : rédige des brouillons uniquement.'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nom')->searchable()->sortable()->weight('medium'),
                TextColumn::make('email')->label('E-mail')->searchable(),
                TextColumn::make('roles.name')->label('Rôle')->badge()
                    ->formatStateUsing(fn (string $state) => self::ROLE_LABELS[$state] ?? $state)
                    ->placeholder('Aucun accès'),
                TextColumn::make('created_at')->label('Créé le')->date('d/m/Y')->sortable()->toggleable(),
            ])
            ->defaultSort('name')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageUsers::route('/')];
    }
}
