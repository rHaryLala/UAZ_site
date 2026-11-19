<?php

namespace App\Filament\Resources\SiteVisits;

use App\Filament\Resources\SiteVisits\Pages\ListSiteVisits;
use App\Models\SiteVisit;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Les visiteurs du site, un par jour : d'où ils viennent (ville, région, pays d'après leur adresse IP, donc approximatif), leur appareil et les pages
 * qu'ils ont consultées, dans l'ordre. Données personnelles : réservé aux personnes qui ont le droit « visitors.view » (le super-administrateur).
 * Chaque ligne regroupe les pages vues d'une même empreinte de visiteur pendant une journée (voir App\Http\Middleware\TrackVisit).
 */
class SiteVisitResource extends Resource
{
    protected static ?string $model = SiteVisit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static string|\UnitEnum|null $navigationGroup = 'Personnes & messages';

    protected static ?string $modelLabel = 'visiteur';

    protected static ?string $pluralModelLabel = 'visiteurs';

    protected static ?string $navigationLabel = 'Visiteurs du site';

    protected static ?int $navigationSort = 5;

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->can('visitors.view');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    /** Une ligne par visiteur et par jour (MIN(id) sert d'identifiant à la ligne). */
    public static function getEloquentQuery(): Builder
    {
        return SiteVisit::query()
            ->selectRaw('MIN(id) as id, visited_on, visitor, MIN(visited_at) as first_at, MAX(visited_at) as last_at, COUNT(*) as pages,'
                .' MAX(country_code) as country_code, MAX(country) as country, MAX(region) as region, MAX(city) as city,'
                .' MAX(device) as device, MAX(browser) as browser, MAX(referrer) as referrer, MAX(locale) as locale')
            ->groupBy('visited_on', 'visitor');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('last_at')->label('Dernière page vue')->dateTime('d/m/Y H:i')
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('last_at', $direction)),
                TextColumn::make('visitor')->label('Visiteur')->formatStateUsing(fn (string $state) => '#'.strtoupper(substr($state, 0, 6)))
                    ->tooltip('Numéro anonyme, valable pour la journée : il change le lendemain.')->fontFamily('mono'),
                TextColumn::make('place')->label('Lieu (approximatif)')
                    ->state(fn (SiteVisit $record) => self::place($record))
                    ->placeholder('Lieu inconnu')->searchable(query: fn (Builder $query, string $search) => $query->where(fn (Builder $q) => $q
                    ->where('city', 'like', "%{$search}%")->orWhere('region', 'like', "%{$search}%")->orWhere('country', 'like', "%{$search}%"))),
                TextColumn::make('client')->label('Appareil')
                    ->state(fn (SiteVisit $record) => trim(match ($record->device) {
                        'mobile' => 'Téléphone', 'tablet' => 'Tablette', 'desktop' => 'Ordinateur', default => '',
                    }.($record->browser ? ' · '.$record->browser : ''), ' ·')),
                TextColumn::make('pages')->label('Pages vues')->numeric()->alignEnd()
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('pages', $direction)),
                TextColumn::make('referrer')->label('Venu de')->placeholder('Accès direct'),
            ])
            ->defaultSort(fn (Builder $query) => $query->orderByDesc('last_at'))
            ->filters([
                Filter::make('period')->label('Période')
                    ->schema([DatePicker::make('from')->label('Du'), DatePicker::make('until')->label('Au')])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->where('visited_on', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->where('visited_on', '<=', $date))),
                SelectFilter::make('country_code')->label('Pays')
                    ->options(fn () => SiteVisit::query()->whereNotNull('country_code')->select('country_code', 'country')->distinct()->orderBy('country')->pluck('country', 'country_code')->all()),
                SelectFilter::make('device')->label('Appareil')->options(['mobile' => 'Téléphone', 'tablet' => 'Tablette', 'desktop' => 'Ordinateur']),
            ])
            ->recordAction('journey')
            ->recordUrl(null)
            ->recordActions([
                Action::make('journey')->label('Parcours')->icon(Heroicon::OutlinedQueueList)
                    ->modalHeading(fn (SiteVisit $record) => 'Visiteur #'.strtoupper(substr($record->visitor, 0, 6)).' · '.CarbonImmutable::parse($record->visited_on)->locale('fr')->isoFormat('dddd D MMMM YYYY'))
                    ->modalDescription(fn (SiteVisit $record) => self::place($record) ?: 'Lieu inconnu')
                    ->modalContent(fn (SiteVisit $record) => view('filament.visits.journey', [
                        'pages' => SiteVisit::query()->where('visited_on', $record->visited_on)->where('visitor', $record->visitor)->orderBy('visited_at')->orderBy('id')->get(),
                        'visit' => $record,
                    ]))
                    ->modalSubmitAction(false)->modalCancelActionLabel('Fermer')->modalWidth('2xl'),
            ])
            ->paginated([25, 50, 100])
            ->emptyStateHeading('Aucun visiteur compté pour l\'instant')
            ->emptyStateDescription('Les visites apparaissent ici dès que le site public est consulté.');
    }

    public static function getPages(): array
    {
        return ['index' => ListSiteVisits::route('/')];
    }

    /** « Antananarivo, Analamanga, Madagascar » : la ville, la région (si elle diffère) et le pays. */
    public static function place(SiteVisit $visit): string
    {
        return collect([$visit->city, $visit->region !== $visit->city ? $visit->region : null, $visit->country])->filter()->unique()->implode(', ');
    }
}
