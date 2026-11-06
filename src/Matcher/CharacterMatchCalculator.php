<?php

declare(strict_types=1);

namespace App\Matcher;

use App\Entity\Character;

/**
 * Ranks characters by how well they match a visitor's preferences.
 *
 * The visitor rates gameplay tags from -2 ("hate it") to +2 ("love it"); unrated tags
 * count as 0. Each character's score is the average of those ratings weighted by the
 * character's tag percentages:
 *
 *     score  = Σ(rating × pct) / Σ(pct)        (−2 … +2)
 *     match% = round((score + 2) / 4 × 100)    (0 … 100)
 *
 * A character without gameplay tags gets 50%. Triggers never change the score; they
 * are reported so they can be highlighted, or the character can be hidden.
 */
final class CharacterMatchCalculator
{
    public const MIN_RATING = -2;
    public const MAX_RATING = 2;

    /**
     * @param list<Character> $characters
     * @param array<int, int> $ratings         gameplay tag id => rating (-2..2)
     * @param list<int>       $avoidedTriggers trigger tag ids
     *
     * @return list<MatchResult> best match first, then by name
     */
    public function rank(array $characters, array $ratings, array $avoidedTriggers = [], bool $hideTriggerHits = false): array
    {
        $avoided = array_flip($avoidedTriggers);
        $results = [];

        foreach ($characters as $character) {
            $result = $this->match($character, $ratings, $avoided);
            if ($hideTriggerHits && $result->hasTriggerHits()) {
                continue;
            }
            $results[] = $result;
        }

        usort($results, static fn (MatchResult $a, MatchResult $b): int => [$b->percentage, $a->character->getName()] <=> [$a->percentage, $b->character->getName()]);

        return $results;
    }

    /**
     * @param array<int, int> $ratings
     * @param array<int, int> $avoided trigger tag id => anything (lookup table)
     */
    public function match(Character $character, array $ratings, array $avoided = []): MatchResult
    {
        $weighted = 0;
        $weights = 0;
        $dislikes = [];
        foreach ($character->getGameplayTags() as $characterTag) {
            $tagId = (int) $characterTag->getTag()?->getId();
            $rating = self::clamp($ratings[$tagId] ?? 0);
            $weighted += $rating * $characterTag->getPercentage();
            $weights += $characterTag->getPercentage();
            if (self::MIN_RATING === $rating) {
                $dislikes[] = $characterTag;
            }
        }

        $score = 0 === $weights ? 0.0 : $weighted / $weights;
        $percentage = (int) round(($score - self::MIN_RATING) / (self::MAX_RATING - self::MIN_RATING) * 100);

        $triggerHits = array_values(array_filter(
            $character->getTriggerTags(),
            static fn ($ct): bool => isset($avoided[(int) $ct->getTag()?->getId()]),
        ));

        return new MatchResult($character, $percentage, $triggerHits, $dislikes);
    }

    public static function clamp(int $rating): int
    {
        return max(self::MIN_RATING, min(self::MAX_RATING, $rating));
    }
}
