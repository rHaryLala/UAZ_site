<?php

namespace App\Models;

use App\Support\Locales;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Menu de la cafétéria pour une semaine : sept jours à partir de `starts_on`, chacun avec trois repas (matin, midi, soir) qui sont des listes
 * de plats. Les plats sont publiés tels qu'ils sont saisis (ils mêlent français, anglais et malgache) : seuls les intitulés du tableau
 * et les dates changent avec la langue du site.
 */
#[Fillable(['starts_on', 'meals', 'note', 'is_active'])]
class CafeteriaMenu extends Model
{
    use HasFactory;

    /** Repas d'une journée, dans l'ordre : clé de stockage => intitulé français (traduit à l'affichage). */
    public const MEALS = ['morning' => 'Matin', 'noon' => 'Midi', 'evening' => 'Soir'];

    public const DAYS = 7;

    protected function casts(): array
    {
        return ['starts_on' => 'date:Y-m-d', 'meals' => 'array', 'is_active' => 'boolean'];
    }

    /** @param  Builder<static>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** Semaines affichées sur le site : celle en cours et les suivantes (une semaine se termine six jours après son premier jour). */
    public function scopeCurrentAndUpcoming(Builder $query): void
    {
        $query->active()->whereDate('starts_on', '>=', Carbon::today()->subDays(self::DAYS - 1))->orderBy('starts_on');
    }

    public function endsOn(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->starts_on)->addDays(self::DAYS - 1);
    }

    /** « 10 au 16 novembre 2026 » : le mois n'est répété au début que si la semaine en change. */
    public function rangeLabel(): string
    {
        $start = CarbonImmutable::parse($this->starts_on);
        $end = $this->endsOn();
        $locale = Locales::current();

        $from = $start->locale($locale)->translatedFormat($start->month === $end->month ? 'j' : 'j F');

        return __('Semaine du :start au :end', ['start' => $from, 'end' => $end->locale($locale)->translatedFormat('j F Y')]);
    }

    /** « 10 – 16 nov. » : libellé court d'un onglet. */
    public function shortRange(): string
    {
        $start = CarbonImmutable::parse($this->starts_on)->locale(Locales::current());
        $end = $this->endsOn()->locale(Locales::current());

        return $start->translatedFormat($start->month === $end->month ? 'j' : 'j M').' – '.$end->translatedFormat('j M');
    }

    /** La semaine est-elle en cours aujourd'hui ? */
    public function isCurrent(): bool
    {
        return Carbon::today()->betweenIncluded(Carbon::parse($this->starts_on), $this->endsOn());
    }

    /**
     * Les sept jours, prêts à afficher.
     *
     * @return list<array{date: CarbonImmutable, name: string, abbr: string, short: string, today: bool, meals: array<string, list<string>>}>
     */
    public function days(): array
    {
        $locale = Locales::current();
        $days = [];

        for ($i = 0; $i < self::DAYS; $i++) {
            $date = CarbonImmutable::parse($this->starts_on)->addDays($i);
            $meals = [];

            foreach (array_keys(self::MEALS) as $key) {
                $meals[$key] = array_values(array_filter(array_map('trim', (array) ($this->meals[$i][$key] ?? [])), fn ($dish) => $dish !== ''));
            }

            $days[] = [
                'date' => $date,
                'name' => $date->locale($locale)->translatedFormat('l'),
                'abbr' => $date->locale($locale)->translatedFormat('D'),
                'short' => $date->locale($locale)->translatedFormat('j M'),
                'today' => $date->isSameDay(Carbon::today()),
                'meals' => $meals,
            ];
        }

        return $days;
    }

    /** Aucun plat saisi sur toute la semaine ? */
    public function isEmpty(): bool
    {
        return collect($this->days())->every(fn (array $day) => collect($day['meals'])->flatten()->isEmpty());
    }
}
