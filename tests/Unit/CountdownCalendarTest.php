<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Block\Type\CountdownBlock;
use App\Entity\LarpEdition;
use PHPUnit\Framework\TestCase;

final class CountdownCalendarTest extends TestCase
{
    public function testMonthGridMarksTheEventDaysMondayFirst(): void
    {
        // August 2026 starts on a Saturday.
        $edition = (new LarpEdition())->setStartDate(new \DateTimeImmutable('2026-08-06'))->setEndDate(new \DateTimeImmutable('2026-08-09'));
        $months = CountdownBlock::calendar($edition, new \DateTimeImmutable('2026-08-01'));

        self::assertCount(1, $months);
        self::assertSame('sierpień 2026', mb_strtolower($months[0]['label']));
        $first = $months[0]['weeks'][0];
        self::assertSame([null, null, null, null, null], \array_slice($first, 0, 5), 'Mon–Fri before the 1st are empty');
        $saturday = $first[5];
        self::assertNotNull($saturday);
        self::assertSame(1, $saturday['day']);
        self::assertTrue($saturday['today']);

        $days = array_merge(...$months[0]['weeks']);
        $event = array_values(array_map(static fn (array $d): int => $d['day'], array_filter($days, static fn (?array $d): bool => null !== $d && $d['event'])));
        self::assertSame([6, 7, 8, 9], $event);
        foreach ($months[0]['weeks'] as $week) {
            self::assertCount(7, $week);
        }
    }

    public function testAnEventAcrossTheMonthEndShowsBothMonths(): void
    {
        $edition = (new LarpEdition())->setStartDate(new \DateTimeImmutable('2027-04-30'))->setEndDate(new \DateTimeImmutable('2027-05-02'));
        $months = CountdownBlock::calendar($edition, new \DateTimeImmutable('2027-01-01'));

        self::assertCount(2, $months);
        self::assertStringContainsStringIgnoringCase('kwiecień', $months[0]['label']);
        self::assertStringContainsStringIgnoringCase('maj', $months[1]['label']);
    }

    public function testNoStartDateNoCalendar(): void
    {
        self::assertSame([], CountdownBlock::calendar(new LarpEdition(), new \DateTimeImmutable()));
    }
}
