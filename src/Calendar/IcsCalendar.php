<?php

declare(strict_types=1);

namespace App\Calendar;

use App\Entity\LarpEdition;

/**
 * An iCalendar (.ics) file with one all-day event for a LARP edition, so visitors
 * can add it to Google Calendar, Apple Calendar, Outlook, ... (RFC 5545).
 */
final class IcsCalendar
{
    /** Null when the edition has no start date. */
    public static function forEdition(LarpEdition $edition, string $url, string $organizer, ?\DateTimeImmutable $now = null): ?string
    {
        $start = $edition->getStartDate();
        $larp = $edition->getLarp();
        if (null === $start || null === $larp) {
            return null;
        }
        // All-day events end on the day after the last day.
        $end = ($edition->getEndDate() ?? $start)->modify('+1 day');
        $now ??= new \DateTimeImmutable();

        $description = trim(implode("\n\n", array_filter([
            $larp->getTeaser(),
            $edition->getFacebookEventUrl(),
            $url,
        ])));

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//'.self::text($organizer).'//LARP//PL',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:larp-edition-'.$edition->getId().'@'.(parse_url($url, \PHP_URL_HOST) ?: 'larp'),
            'DTSTAMP:'.$now->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z'),
            'DTSTART;VALUE=DATE:'.$start->format('Ymd'),
            'DTEND;VALUE=DATE:'.$end->format('Ymd'),
            'SUMMARY:'.self::text(trim($larp->getName().' — '.$edition->getName(), ' —')),
        ];
        if (null !== $edition->getLocation()) {
            $lines[] = 'LOCATION:'.self::text($edition->getLocation());
        }
        if ('' !== $description) {
            $lines[] = 'DESCRIPTION:'.self::text($description);
        }
        $lines[] = 'URL:'.$url;
        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(self::fold(...), $lines))."\r\n";
    }

    /** Escapes a TEXT value. */
    private static function text(string $value): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], $value);
    }

    /** Lines longer than 75 bytes continue on the next line after a space (without splitting characters). */
    private static function fold(string $line): string
    {
        $out = '';
        $current = '';
        foreach (mb_str_split($line) as $char) {
            if (\strlen($current.$char) > ('' === $out ? 75 : 74)) {
                $out .= ('' === $out ? '' : "\r\n ").$current;
                $current = '';
            }
            $current .= $char;
        }

        return $out.('' === $out ? '' : "\r\n ").$current;
    }
}
