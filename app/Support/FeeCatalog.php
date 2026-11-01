<?php

namespace App\Support;

/**
 * Frais académiques : met les tableaux de config/fees.php sous la forme utilisée par le simulateur de la page
 * (resources/js/fees-sim.js) et par les tableaux accessibles. Aucun montant n'est calculé « à part » : les lignes viennent de la config,
 * les totaux sont leur somme, et les totaux imprimés sur les tableaux officiels servent de contrôle (voir les tests).
 */
final class FeeCatalog
{
    /** Position du régime dans la paire de colonnes d'une année. */
    private const STATUS_OFFSET = ['interne' => 0, 'externe' => 1];

    /**
     * Lignes non vides d'un semestre de Licence pour une année (0 = remise à niveau … 3 = 3e année) et un régime.
     *
     * @param  array{rows: list<array<string, mixed>>}  $semester
     * @return list<array{label: string, note: ?string, group: string, amount: int}>
     */
    public static function licenceLines(array $semester, int $yearIndex, string $status): array
    {
        $column = $yearIndex * 2 + self::STATUS_OFFSET[$status];
        $lines = [];

        foreach ($semester['rows'] as $row) {
            $amount = $row['values'][$column] ?? null;

            if ($amount !== null) {
                $lines[] = ['label' => $row['label'], 'note' => $row['note'] ?? null, 'group' => $row['group'], 'amount' => $amount];
            }
        }

        return $lines;
    }

    /** @param  list<array{amount: int}>  $lines */
    public static function sum(array $lines): int
    {
        return (int) array_sum(array_column($lines, 'amount'));
    }

    /**
     * Total imprimé sur le tableau officiel pour une année et un régime de Licence.
     *
     * @param  array{totals: list<int>}  $semester
     */
    public static function licencePublishedTotal(array $semester, int $yearIndex, string $status): int
    {
        return $semester['totals'][$yearIndex * 2 + self::STATUS_OFFSET[$status]];
    }

    /**
     * Catalogue complet : licence[département][année][régime][semestre] et master[parcours][semestre], chacun { lines, total }.
     *
     * @return array<string, mixed>
     */
    public static function catalog(): array
    {
        $catalog = ['licence' => [], 'master' => []];
        $years = array_keys(config('fees.licence.years'));

        foreach (config('fees.licence.departments') as $department => $data) {
            foreach ($years as $yearIndex => $year) {
                foreach (array_keys(self::STATUS_OFFSET) as $status) {
                    foreach ($data['semesters'] as $number => $semester) {
                        $lines = self::licenceLines($semester, $yearIndex, $status);
                        $catalog['licence'][$department][$year][$status][$number] = ['lines' => $lines, 'total' => self::sum($lines)];
                    }
                }
            }
        }

        foreach (config('fees.master.tracks') as $track => $data) {
            foreach ($data['semesters'] as $number => $semester) {
                $lines = array_map(fn (array $row) => ['label' => $row['label'], 'note' => $row['note'], 'group' => $row['group'], 'amount' => $row['amount']], $semester['rows']);
                $catalog['master'][$track][$number] = ['lines' => $lines, 'total' => self::sum($lines)];
            }
        }

        return $catalog;
    }

    /** Montant formaté à la française, espaces insécables : 2 354 000. */
    public static function format(?int $amount): string
    {
        return $amount === null ? '–' : str_replace(' ', "\u{00A0}", number_format($amount, 0, ',', ' '));
    }
}
