<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Filament\Support\RowActions;
use App\Models\User;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|\UnitEnum|null $navigationGroup = 'Site & médias';

    protected static ?int $navigationSort = 5;

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
                ToggleColumn::make('is_active')->label('Actif')
                    ->onColor('success')->offColor('gray')
                    ->disabled(fn (User $record) => ! $record->canBeDeactivated())
                    ->tooltip(fn (User $record) => match (true) {
                        $record->hasRole('super-admin') => 'Un super-administrateur ne peut pas être désactivé.',
                        $record->is(auth()->user()) => 'Vous ne pouvez pas désactiver votre propre compte.',
                        $record->isActive() => 'Actif : cliquez pour désactiver ce compte.',
                        default => 'Désactivé : cliquez pour réactiver ce compte.',
                    })
                    // Contrôle côté serveur (l'interrupteur grisé n'est qu'une aide) : on n'enregistre que ce que le garde-fou autorise.
                    ->updateStateUsing(function (User $record, bool $state): bool {
                        if (! $record->canBeDeactivated()) {
                            Notification::make()->danger()->title('Ce compte ne peut pas être désactivé')->send();

                            return $record->isActive();
                        }

                        $record->forceFill(['is_active' => $state])->save();

                        Notification::make()->success()
                            ->title($state ? 'Compte activé' : 'Compte désactivé')
                            ->body($state ? $record->name.' peut de nouveau se connecter.' : $record->name.' ne peut plus se connecter (sa session en cours est coupée).')
                            ->send();

                        return $state;
                    }),
                TextColumn::make('created_at')->label('Créé le')->date('d/m/Y')->sortable()->toggleable(),
            ])
            ->recordClasses(fn (User $record) => $record->isActive() ? null : 'opacity-60')
            ->filters([
                TernaryFilter::make('is_active')->label('Compte')
                    ->trueLabel('Actifs')->falseLabel('Désactivés')
                    ->queries(
                        true: fn (Builder $query) => $query->where('is_active', true),
                        false: fn (Builder $query) => $query->where('is_active', false),
                        blank: fn (Builder $query) => $query,
                    ),
            ])
            ->defaultSort('name')
            ->recordActions(RowActions::editDelete())
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageUsers::route('/')];
    }
}
