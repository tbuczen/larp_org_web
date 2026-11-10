<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Calendar\IcsCalendar;
use App\Entity\Larp;
use App\Entity\LarpEdition;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class IcsCalendarTest extends TestCase
{
    public function testAllDayEventForTheEdition(): void
    {
        $larp = (new Larp())->setName('Pradawne Zwoje')->setTeaser('Larp terenowy w świecie Skyrim; magia, smoki.');
        $edition = (new LarpEdition())->setName('3. edycja')
            ->setStartDate(new \DateTimeImmutable('2026-08-06'))
            ->setEndDate(new \DateTimeImmutable('2026-08-09'))
            ->setLocation('Czyżowice')
            ->setFacebookEventUrl('https://fb.me/e/hlDppZ9HB');
        $larp->addEdition($edition);

        $ics = (string) IcsCalendar::forEdition($edition, 'https://lastajemnic.org/larp/pradawne-zwoje', 'Las Tajemnic', new \DateTimeImmutable('2026-05-01 12:00:00 UTC'));

        self::assertStringStartsWith("BEGIN:VCALENDAR\r\nVERSION:2.0\r\n", $ics);
        self::assertStringEndsWith("END:VCALENDAR\r\n", $ics);
        self::assertStringContainsString("DTSTART;VALUE=DATE:20260806\r\n", $ics);
        self::assertStringContainsString("DTEND;VALUE=DATE:20260810\r\n", $ics, 'all-day events end the day after');
        self::assertStringContainsString("DTSTAMP:20260501T120000Z\r\n", $ics);
        self::assertStringContainsString("SUMMARY:Pradawne Zwoje — 3. edycja\r\n", $ics);
        self::assertStringContainsString("LOCATION:Czyżowice\r\n", $ics);
        self::assertStringContainsString('Skyrim\; magia\, smoki.', $ics, 'TEXT values are escaped');

        foreach (explode("\r\n", $ics) as $line) {
            self::assertLessThanOrEqual(75, \strlen($line), 'lines are folded at 75 bytes: '.$line);
        }
        // Unfolding gives back the description with the event link.
        self::assertStringContainsString('https://fb.me/e/hlDppZ9HB', str_replace("\r\n ", '', $ics));
    }

    public function testNoDatesNoCalendar(): void
    {
        $edition = (new LarpEdition())->setName('2027');
        (new Larp())->setName('Vault 47')->addEdition($edition);

        self::assertNull(IcsCalendar::forEdition($edition, 'https://example.org', 'Org'));
    }

    public function testFacebookEventAddressIsChecked(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $edition = (new LarpEdition())->setName('1. edycja');

        foreach (['https://fb.me/e/69mffUNUK', 'https://www.facebook.com/events/123456/', 'https://m.facebook.com/events/1'] as $ok) {
            self::assertCount(0, $validator->validateProperty($edition->setFacebookEventUrl($ok), 'facebookEventUrl'), $ok);
        }
        foreach (['https://www.facebook.com/pradawnezwoje', 'http://fb.me/e/x', 'https://evil.example/events/1'] as $bad) {
            self::assertCount(1, $validator->validateProperty($edition->setFacebookEventUrl($bad), 'facebookEventUrl'), $bad);
        }
    }

    public function testUpcoming(): void
    {
        $today = new \DateTimeImmutable('2026-08-08');
        $edition = (new LarpEdition())->setStartDate(new \DateTimeImmutable('2026-08-06'))->setEndDate(new \DateTimeImmutable('2026-08-09'));

        self::assertTrue($edition->isUpcoming($today), 'still running');
        self::assertFalse($edition->isUpcoming(new \DateTimeImmutable('2026-08-10')));
        self::assertFalse((new LarpEdition())->isUpcoming($today), 'no dates');

        self::assertFalse($edition->isPast($today), 'still running is not past');
        self::assertTrue($edition->isPast(new \DateTimeImmutable('2026-08-10')));
        self::assertFalse((new LarpEdition())->isPast($today), 'without dates it is not past either');
    }
}
