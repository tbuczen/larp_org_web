<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Character;
use App\Entity\Tag;
use App\Enum\TagType;
use App\Matcher\CharacterMatchCalculator;
use PHPUnit\Framework\TestCase;

final class CharacterMatchCalculatorTest extends TestCase
{
    private CharacterMatchCalculator $calculator;

    /** @var array<string, Tag> */
    private array $tags = [];

    protected function setUp(): void
    {
        $this->calculator = new CharacterMatchCalculator();
        $id = 1;
        foreach (['drama' => TagType::Gameplay, 'combat' => TagType::Gameplay, 'intrigue' => TagType::Gameplay, 'death' => TagType::Trigger, 'gore' => TagType::Trigger] as $name => $type) {
            $tag = (new Tag())->setName($name)->setType($type);
            (new \ReflectionProperty(Tag::class, 'id'))->setValue($tag, $id++);
            $this->tags[$name] = $tag;
        }
    }

    public function testNoPreferencesGivesFiftyPercent(): void
    {
        $character = $this->character('A', ['drama' => 60, 'combat' => 40]);

        self::assertSame(50, $this->calculator->match($character, [])->percentage);
    }

    public function testCharacterWithoutGameplayTagsGetsFiftyPercent(): void
    {
        self::assertSame(50, $this->calculator->match($this->character('A', []), $this->ratings(['drama' => 2]))->percentage);
    }

    public function testRatingsAreWeightedByPercentage(): void
    {
        // Loves drama (+2), hates combat (-2). Character: 75% drama, 25% combat.
        // score = (2×75 − 2×25) / 100 = 1.0 → (1 + 2) / 4 = 75%
        $character = $this->character('A', ['drama' => 75, 'combat' => 25]);

        self::assertSame(75, $this->calculator->match($character, $this->ratings(['drama' => 2, 'combat' => -2]))->percentage);
    }

    public function testExtremes(): void
    {
        $character = $this->character('A', ['drama' => 30, 'combat' => 70]);

        self::assertSame(100, $this->calculator->match($character, $this->ratings(['drama' => 2, 'combat' => 2]))->percentage);
        self::assertSame(0, $this->calculator->match($character, $this->ratings(['drama' => -2, 'combat' => -2]))->percentage);
    }

    public function testUnratedTagsCountAsNeutral(): void
    {
        // Only drama rated (+2), 50% drama + 50% intrigue (unrated) → score 1 → 75%
        $character = $this->character('A', ['drama' => 50, 'intrigue' => 50]);

        self::assertSame(75, $this->calculator->match($character, $this->ratings(['drama' => 2]))->percentage);
    }

    public function testRatingsOutsideTheScaleAreClamped(): void
    {
        $character = $this->character('A', ['drama' => 100]);

        self::assertSame(100, $this->calculator->match($character, $this->ratings(['drama' => 99]))->percentage);
    }

    public function testRankSortsByMatchThenName(): void
    {
        $ratings = $this->ratings(['drama' => 2, 'combat' => -2]);
        $results = $this->calculator->rank([
            $this->character('Fighter', ['combat' => 100]),
            $this->character('Bard', ['drama' => 100]),
            $this->character('Actor', ['drama' => 100]),
            $this->character('Mixed', ['drama' => 50, 'combat' => 50]),
        ], $ratings);

        self::assertSame(['Actor', 'Bard', 'Mixed', 'Fighter'], array_map(static fn ($r) => $r->character->getName(), $results));
        self::assertSame([100, 100, 50, 0], array_map(static fn ($r) => $r->percentage, $results));
    }

    public function testTriggerHitsAreReportedWithoutChangingTheScore(): void
    {
        $character = $this->character('A', ['drama' => 100], ['death' => 80, 'gore' => 20]);

        $result = $this->calculator->match($character, $this->ratings(['drama' => 2]), [$this->tags['death']->getId() => 0]);

        self::assertSame(100, $result->percentage);
        self::assertSame(['death'], array_map(static fn ($ct) => $ct->getTag()?->getName(), $result->triggerHits));
    }

    public function testCharactersWithTriggersCanBeHidden(): void
    {
        $characters = [
            $this->character('Safe', ['drama' => 100]),
            $this->character('Dark', ['drama' => 100], ['death' => 50]),
        ];
        $avoided = [(int) $this->tags['death']->getId()];

        self::assertCount(2, $this->calculator->rank($characters, [], $avoided));
        $hidden = $this->calculator->rank($characters, [], $avoided, hideTriggerHits: true);
        self::assertSame(['Safe'], array_map(static fn ($r) => $r->character->getName(), $hidden));
    }

    public function testDislikesListTagsRatedHateIt(): void
    {
        $character = $this->character('A', ['drama' => 50, 'combat' => 30, 'intrigue' => 20]);

        $result = $this->calculator->match($character, $this->ratings(['combat' => -2, 'intrigue' => -1]));

        self::assertSame(['combat'], array_map(static fn ($ct) => $ct->getTag()?->getName(), $result->dislikes));
    }

    public function testLabels(): void
    {
        $character = $this->character('A', ['drama' => 100]);
        $label = fn (int $rating): string => $this->calculator->match($character, $this->ratings(['drama' => $rating]))->getLabel();

        self::assertSame('great', $label(2));   // 100%
        self::assertSame('good', $label(1));    // 75%
        self::assertSame('maybe', $label(0));   // 50%
        self::assertSame('poor', $label(-1));   // 25%
    }

    /**
     * @param array<string, int> $gameplay
     * @param array<string, int> $triggers
     */
    private function character(string $name, array $gameplay, array $triggers = []): Character
    {
        $character = (new Character())->setName($name);
        foreach ($gameplay + $triggers as $tag => $percentage) {
            $character->tag($this->tags[$tag], $percentage);
        }

        return $character;
    }

    /**
     * @param array<string, int> $byName
     *
     * @return array<int, int>
     */
    private function ratings(array $byName): array
    {
        $ratings = [];
        foreach ($byName as $name => $rating) {
            $ratings[(int) $this->tags[$name]->getId()] = $rating;
        }

        return $ratings;
    }
}
