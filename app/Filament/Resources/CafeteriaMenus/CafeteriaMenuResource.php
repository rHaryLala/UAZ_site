<?php

namespace App\Filament\Resources\CafeteriaMenus;

use App\Filament\Resources\CafeteriaMenus\Pages\CreateCafeteriaMenu;
use App\Filament\Resources\CafeteriaMenus\Pages\EditCafeteriaMenu;
use App\Filament\Resources\CafeteriaMenus\Pages\ListCafeteriaMenus;
use App\Models\CafeteriaMenu;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class CafeteriaMenuResource extends Resource
{
    protected static ?string $model = CafeteriaMenu::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCake;

    protected static string|\UnitEnum|null $navigationGroup = 'Contenus';

    protected static ?string $modelLabel = 'menu de la semaine';

    protected static ?string $pluralModelLabel = 'menus de la cafétéria';

    protected static ?string $navigationLabel = 'Menus de la cafétéria';

    protected static ?int $navigationSort = 6;

    // Filament met une majuscule à chaque mot (« Menus De La Cafétéria ») : intitulés fixés à la main.
    protected static ?string $breadcrumb = 'Menus de la cafétéria';

    public static function form(Schema $schema): Schema
    {
        $days = collect(range(0, CafeteriaMenu::DAYS - 1))->map(fn (int $i) => Tab::make(fn (Get $get): string => self::dayLabel($get('starts_on'), $i))
            ->key("jour-$i")
            ->schema([
                Grid::make(['default' => 1, 'lg' => 3])->schema(
                    collect(CafeteriaMenu::MEALS)->map(fn (string $label, string $meal) => Textarea::make("meals.$i.$meal")
                        ->label($label)
                        ->rows(7)
                        ->placeholder("Un plat par ligne\nEx. : Riz cantonnais")
                        // Les plats sont stockés en liste ; le champ les montre un par ligne.
                        ->formatStateUsing(fn ($state) => is_array($state) ? implode("\n", $state) : (string) $state)
                        ->dehydrateStateUsing(fn ($state) => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $state) ?: []), fn ($dish) => $dish !== ''))))
                        ->values()->all(),
                ),
            ]))->all();

        return $schema->components([
            Section::make('Semaine')->columns(2)->schema([
                DatePicker::make('starts_on')->label('Premier jour de la semaine')->native(false)->displayFormat('d/m/Y')->required()->live()
                    ->unique(ignoreRecord: true)
                    ->default(fn () => self::nextWeekStart())
                    ->helperText('La semaine va du dimanche au samedi : choisissez le dimanche. Une semaine = sept jours à partir de cette date.')
                    ->validationMessages(['unique' => 'Il y a déjà un menu pour cette semaine : modifiez-le ou choisissez une autre date.']),
                Toggle::make('is_active')->label('Affiché sur le site')->default(true)
                    ->helperText('Seuls la semaine en cours et les semaines à venir apparaissent sur la page Cafétéria.'),
                TextInput::make('note')->label('Mention sous le tableau (facultatif)')->maxLength(300)->columnSpanFull()
                    ->placeholder('Ex. : Le menu peut être modifié selon les arrivages.'),
            ]),
            Section::make('Repas de la semaine')
                ->description('Un onglet par jour. Écrivez un plat par ligne, comme sur le menu papier ; un repas laissé vide n\'est pas affiché.')
                ->schema([Tabs::make('Jours')->tabs($days)->persistTabInQueryString()]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('starts_on')->label('Semaine')->sortable()->weight('medium')
                    ->formatStateUsing(fn ($state, CafeteriaMenu $record) => $record->rangeLabel()),
                TextColumn::make('status')->label('Période')->badge()
                    ->state(fn (CafeteriaMenu $record) => self::period($record))
                    ->color(fn (string $state) => match ($state) {
                        'En cours' => 'success',
                        'À venir' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('dishes')->label('Plats')->state(fn (CafeteriaMenu $record) => collect($record->days())->pluck('meals')->flatten()->count()),
                ToggleColumn::make('is_active')->label('Affiché'),
            ])
            ->defaultSort('starts_on', 'desc')
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    self::duplicateAction(),
                    DeleteAction::make(),
                ])->icon(Heroicon::OutlinedEllipsisVertical)->tooltip('Actions'),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    /** Copie la semaine vers une autre date : les menus de l'UAZ tournent sur plusieurs semaines, on part donc de la précédente. */
    public static function duplicateAction(): Action
    {
        return Action::make('duplicate')
            ->label('Dupliquer vers une autre semaine')
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->modalHeading('Dupliquer ce menu')
            ->modalDescription('La copie n\'est pas affichée sur le site tant que vous ne l\'avez pas relue et activée.')
            ->modalSubmitActionLabel('Créer la copie')
            ->fillForm(fn (CafeteriaMenu $record) => ['starts_on' => $record->starts_on->copy()->addWeek()->toDateString()])
            ->schema([
                DatePicker::make('starts_on')->label('Premier jour de la nouvelle semaine')->native(false)->displayFormat('d/m/Y')->required()
                    ->unique(CafeteriaMenu::class, 'starts_on')
                    ->validationMessages(['unique' => 'Il y a déjà un menu pour cette semaine.']),
            ])
            ->action(function (CafeteriaMenu $record, array $data): void {
                $copy = CafeteriaMenu::create(['starts_on' => $data['starts_on'], 'meals' => $record->meals, 'note' => $record->note, 'is_active' => false]);

                Notification::make()->success()->title('Menu copié')
                    ->body('Semaine du '.Carbon::parse($copy->starts_on)->locale('fr')->translatedFormat('j F Y').' : relisez-le, puis activez « Affiché sur le site ».')
                    ->send();
            });
    }

    /** Libellé d'un onglet : « dimanche 8 nov. », ou « Jour 1 » tant que la date n'est pas choisie. */
    public static function dayLabel(mixed $startsOn, int $index): string
    {
        if (blank($startsOn)) {
            return 'Jour '.($index + 1);
        }

        try {
            return Carbon::parse($startsOn)->addDays($index)->locale('fr')->translatedFormat('l j M');
        } catch (\Throwable) {
            return 'Jour '.($index + 1);
        }
    }

    /** Semaine suivant le dernier menu enregistré (sinon, le prochain dimanche). */
    public static function nextWeekStart(): string
    {
        $last = CafeteriaMenu::query()->max('starts_on');

        return $last ? Carbon::parse($last)->addWeek()->toDateString() : Carbon::today()->next(Carbon::SUNDAY)->toDateString();
    }

    public static function period(CafeteriaMenu $menu): string
    {
        $today = Carbon::today();

        return match (true) {
            $menu->endsOn()->lt($today) => 'Passée',
            Carbon::parse($menu->starts_on)->gt($today) => 'À venir',
            default => 'En cours',
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCafeteriaMenus::route('/'),
            'create' => CreateCafeteriaMenu::route('/create'),
            'edit' => EditCafeteriaMenu::route('/{record}/edit'),
        ];
    }
}
