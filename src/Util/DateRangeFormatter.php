<?php

declare(strict_types=1);

namespace App\Util;

/**
 * Formats date ranges the way Polish LARP announcements usually do, with Roman
 * month numerals: "17–18.VII.2026", "30.VII–2.VIII.2026", "28.XII.2026–2.I.2027".
 */
final class DateRangeFormatter
{
    private const ROMAN = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

    public static function format(?\DateTimeInterface $start, ?\DateTimeInterface $end = null): ?string
    {
        if (null === $start) {
            return null === $end ? null : self::single($end);
        }
        if (null === $end || $start->format('Y-m-d') === $end->format('Y-m-d')) {
            return self::single($start);
        }

        $day = static fn (\DateTimeInterface $d): string => $d->format('j');
        $month = static fn (\DateTimeInterface $d): string => self::ROMAN[(int) $d->format('n') - 1];

        if ($start->format('Y') !== $end->format('Y')) {
            return self::single($start).'–'.self::single($end);
        }
        if ($start->format('n') !== $end->format('n')) {
            return sprintf('%s.%s–%s.%s.%s', $day($start), $month($start), $day($end), $month($end), $end->format('Y'));
        }

        return sprintf('%s–%s.%s.%s', $day($start), $day($end), $month($end), $end->format('Y'));
    }

    private static function single(\DateTimeInterface $date): string
    {
        return sprintf('%s.%s.%s', $date->format('j'), self::ROMAN[(int) $date->format('n') - 1], $date->format('Y'));
    }
}
