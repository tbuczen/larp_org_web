<?php

declare(strict_types=1);

namespace App\Matcher;

use App\Entity\Character;
use App\Entity\CharacterTag;

/**
 * How well one character fits a visitor's preferences.
 */
final readonly class MatchResult
{
    /**
     * @param list<CharacterTag> $triggerHits triggers the visitor wants to avoid that this character has
     * @param list<CharacterTag> $dislikes    gameplay tags the visitor rated "hate it" that this character has
     */
    public function __construct(
        public Character $character,
        public int $percentage,
        public array $triggerHits,
        public array $dislikes,
    ) {
    }

    /** Translation key: great / good / maybe / poor (same thresholds as zapisy.krak-on.info). */
    public function getLabel(): string
    {
        return match (true) {
            $this->percentage >= 80 => 'great',
            $this->percentage >= 60 => 'good',
            $this->percentage >= 40 => 'maybe',
            default => 'poor',
        };
    }

    public function hasTriggerHits(): bool
    {
        return [] !== $this->triggerHits;
    }
}
