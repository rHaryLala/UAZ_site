<?php

namespace App\Support;

use App\Models\Event;
use Carbon\CarbonInterface;

/**
 * « Ajouter à mon agenda » : le fichier .ics d'un événement (lu par Google Agenda, Outlook, Apple Calendrier…) et le lien Google Agenda.
 * Tout vient des dates saisies : sans heure de fin, l'événement n'en reçoit pas (rien n'est supposé).
 */
final class EventCalendar
{
    public static function ics(Event $event): string
    {
        $allDay = $event->is_all_day;
        $lines = [
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Université Adventiste Zurcher//Site//FR', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:event-'.$event->getKey().'@'.parse_url(url('/'), PHP_URL_HOST),
            'DTSTAMP:'.self::utc(now()),
            $allDay ? 'DTSTART;VALUE=DATE:'.$event->starts_at->format('Ymd') : 'DTSTART:'.self::utc($event->starts_at),
        ];

        if ($allDay) {
            // Un événement « toute la journée » se termine, en iCalendar, le lendemain de son dernier jour.
            $lines[] = 'DTEND;VALUE=DATE:'.($event->ends_at ?? $event->starts_at)->copy()->addDay()->format('Ymd');
        } elseif ($event->ends_at) {
            $lines[] = 'DTEND:'.self::utc($event->ends_at);
        }

        $lines[] = 'SUMMARY:'.self::escape($event->title);
        if ($description = trim(strip_tags((string) ($event->summary ?: $event->description)))) {
            $lines[] = 'DESCRIPTION:'.self::escape($description);
        }
        if ($event->location) {
            $lines[] = 'LOCATION:'.self::escape($event->location);
        }
        $lines[] = 'URL:'.$event->url();
        array_push($lines, 'END:VEVENT', 'END:VCALENDAR');

        return implode("\r\n", array_map(self::fold(...), $lines))."\r\n";
    }

    public static function googleUrl(Event $event): string
    {
        $start = $event->is_all_day ? $event->starts_at->format('Ymd') : self::utc($event->starts_at);
        $end = $event->is_all_day
            ? ($event->ends_at ?? $event->starts_at)->copy()->addDay()->format('Ymd')
            : self::utc($event->ends_at ?? $event->starts_at);

        return 'https://calendar.google.com/calendar/render?'.http_build_query(array_filter([
            'action' => 'TEMPLATE', 'text' => $event->title, 'dates' => $start.'/'.$end,
            'details' => trim(strip_tags((string) ($event->summary ?: $event->description))).' '.$event->url(),
            'location' => $event->location,
        ]), '', '&', PHP_QUERY_RFC3986);
    }

    private static function utc(CarbonInterface $date): string
    {
        return $date->copy()->utc()->format('Ymd\THis\Z');
    }

    private static function escape(string $text): string
    {
        return str_replace(["\\", ';', ',', "\r\n", "\n", "\r"], ['\\', '\;', '\,', '\n', '\n', '\n'], $text);
    }

    /** Les lignes d'un fichier iCalendar ne dépassent pas 75 octets : la suite commence par une espace. */
    private static function fold(string $line): string
    {
        $parts = [];
        while (strlen($line) > 75) {
            $cut = 75;
            // On ne coupe pas au milieu d'un caractère UTF-8.
            while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) {
                $cut--;
            }
            $parts[] = substr($line, 0, $cut);
            $line = ' '.substr($line, $cut);
        }
        $parts[] = $line;

        return implode("\r\n", $parts);
    }
}
