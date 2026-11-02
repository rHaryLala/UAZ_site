<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Le « cadran de l'année » du héros des événements : un cercle de douze mois sur lequel on place aujourd'hui et les événements à venir.
 * Tout est calculé à partir de dates réelles : aucun événement n'est ajouté, aucune date n'est inventée.
 */
final class EventDial
{
    public const CENTER = 200;

    /** Angle (0 à 360°, 0 = 1er janvier, en haut, sens horaire) d'une date dans son année. */
    public static function angle(CarbonInterface $date): float
    {
        $days = $date->isLeapYear() ? 366 : 365;

        return round((($date->dayOfYear - 1) + $date->secondsSinceMidnight() / 86400) / $days * 360, 2);
    }

    /** Écart horaire de $from à $to, toujours vers l'avant (de 0 à 360°). */
    public static function sweep(float $from, float $to): float
    {
        return fmod($to - $from + 360, 360);
    }

    /** Point du cercle de rayon $radius à l'angle donné, dans la boîte 400 × 400 du cadran. */
    public static function point(float $angle, float $radius): array
    {
        $rad = deg2rad($angle - 90);

        return [round(self::CENTER + $radius * cos($rad), 2), round(self::CENTER + $radius * sin($rad), 2)];
    }

    /** Arc de cercle (attribut « d » d'un tracé SVG) de $from à $to, vers l'avant. */
    public static function arc(float $from, float $to, float $radius): string
    {
        $sweep = self::sweep($from, $to);

        if ($sweep < 0.5) {
            return '';
        }

        [$x1, $y1] = self::point($from, $radius);
        [$x2, $y2] = self::point($from + $sweep, $radius);

        return sprintf('M%s %s A%s %s 0 %d 1 %s %s', $x1, $y1, $radius, $radius, $sweep > 180 ? 1 : 0, $x2, $y2);
    }
}
