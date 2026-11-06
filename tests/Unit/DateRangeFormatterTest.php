<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Util\DateRangeFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DateRangeFormatterTest extends TestCase
{
    /** @return iterable<string, array{?string, ?string, ?string}> */
    public static function ranges(): iterable
    {
        yield 'same month' => ['2026-07-17', '2026-07-18', '17–18.VII.2026'];
        yield 'across months' => ['2026-07-30', '2026-08-02', '30.VII–2.VIII.2026'];
        yield 'across years' => ['2026-12-28', '2027-01-02', '28.XII.2026–2.I.2027'];
        yield 'single day' => ['2026-08-06', null, '6.VIII.2026'];
        yield 'same start and end' => ['2026-08-06', '2026-08-06', '6.VIII.2026'];
        yield 'no dates' => [null, null, null];
    }

    #[DataProvider('ranges')]
    public function testFormat(?string $start, ?string $end, ?string $expected): void
    {
        self::assertSame($expected, DateRangeFormatter::format(
            null === $start ? null : new \DateTimeImmutable($start),
            null === $end ? null : new \DateTimeImmutable($end),
        ));
    }
}
