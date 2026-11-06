<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Character;
use App\Entity\Larp;
use App\Entity\LarpEdition;
use App\Entity\Tag;
use App\Enum\TagType;
use PHPUnit\Framework\TestCase;

final class LarpTest extends TestCase
{
    public function testCurrentEditionIsTheNextUpcomingOne(): void
    {
        $larp = $this->larpWithEditions(['2024-08-08', '2026-08-06', '2025-08-07']);

        $current = $larp->getCurrentEdition(new \DateTimeImmutable('2026-01-01'));

        self::assertSame('2026-08-06', $current?->getStartDate()?->format('Y-m-d'));
    }

    public function testCurrentEditionFallsBackToTheLatest(): void
    {
        $larp = $this->larpWithEditions(['2023-06-01', '2025-06-01', '2024-06-01']);

        $current = $larp->getCurrentEdition(new \DateTimeImmutable('2026-11-06'));

        self::assertSame('2025-06-01', $current?->getStartDate()?->format('Y-m-d'));
    }

    public function testEditionInProgressIsStillCurrent(): void
    {
        $larp = new Larp();
        $larp->addEdition((new LarpEdition())->setName('x')
            ->setStartDate(new \DateTimeImmutable('2026-08-06'))
            ->setEndDate(new \DateTimeImmutable('2026-08-09')));

        self::assertNotNull($larp->getCurrentEdition(new \DateTimeImmutable('2026-08-08')));
    }

    public function testYearRange(): void
    {
        self::assertSame('2023–2025', $this->larpWithEditions(['2024-06-01', '2023-06-01', '2025-06-01'])->getYearRange());
        self::assertSame('2023', $this->larpWithEditions(['2023-09-01'])->getYearRange());
        self::assertNull((new Larp())->getYearRange());
    }

    public function testTagsAreSplitByTypeAndSortedByPercentage(): void
    {
        $drama = (new Tag())->setName('Drama')->setType(TagType::Gameplay);
        $combat = (new Tag())->setName('Combat')->setType(TagType::Gameplay);
        $death = (new Tag())->setName('Death')->setType(TagType::Trigger);

        $character = (new Character())->tag($drama, 30)->tag($death, 50)->tag($combat, 70);

        self::assertSame(['Combat', 'Drama'], array_map(static fn ($ct) => $ct->getTag()?->getName(), $character->getGameplayTags()));
        self::assertSame(['Death'], array_map(static fn ($ct) => $ct->getTag()?->getName(), $character->getTriggerTags()));
        self::assertSame(100, $character->getGameplayPercentageTotal());
    }

    /** @param list<string> $starts */
    private function larpWithEditions(array $starts): Larp
    {
        $larp = (new Larp())->setName('Test');
        foreach ($starts as $i => $start) {
            $larp->addEdition((new LarpEdition())->setName((string) $i)->setStartDate(new \DateTimeImmutable($start)));
        }

        return $larp;
    }
}
