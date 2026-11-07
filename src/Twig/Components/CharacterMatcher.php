<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\Character;
use App\Entity\Larp;
use App\Entity\Tag;
use App\Enum\CharacterAvailability;
use App\Enum\CharacterCasting;
use App\Enum\LarpStatus;
use App\Enum\TagType;
use App\Matcher\CharacterMatchCalculator;
use App\Matcher\MatchResult;
use App\Repository\CharacterRepository;
use App\Repository\LarpRepository;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\Metadata\UrlMapping;

/**
 * Public character matcher (issue #12): visitors rate gameplay tags, tick triggers to
 * avoid, and see characters ranked by match. Re-renders on every change; all state is
 * kept in the URL so a result can be shared or bookmarked.
 *
 * Usage: <twig:CharacterMatcher larpSlug="pradawne-zwoje" /> for one LARP, or without
 * larpSlug for every currently organized LARP.
 */
#[AsLiveComponent('CharacterMatcher')]
final class CharacterMatcher
{
    use DefaultActionTrait;

    /** Fixed by the page: limit to one LARP. Null = all currently organized LARPs. */
    #[LiveProp]
    public ?string $larpSlug = null;

    /** @var array<int|string, int|string> gameplay tag id => rating (-2..2) */
    #[LiveProp(writable: true, url: new UrlMapping(as: 'ocena'))]
    public array $ratings = [];

    /** @var list<int|string> trigger tag ids to avoid */
    #[LiveProp(writable: true, url: new UrlMapping(as: 'triggery'))]
    public array $triggers = [];

    // Filters are null when off, so they stay out of the URL (it skips null values);
    // normalizeFilters() turns false / '' from the form back into null.

    #[LiveProp(writable: true, url: new UrlMapping(as: 'ukryj'), onUpdated: 'normalizeFilters')]
    public ?bool $hideTriggers = null;

    /** Casting filter: null (any) or a CharacterCasting value. */
    #[LiveProp(writable: true, url: new UrlMapping(as: 'obsada'), onUpdated: 'normalizeFilters')]
    public ?string $casting = null;

    #[LiveProp(writable: true, url: new UrlMapping(as: 'wolne'), onUpdated: 'normalizeFilters')]
    public ?bool $onlyAvailable = null;

    /** LARP filter on the all-LARPs page: null or a LARP slug. */
    #[LiveProp(writable: true, url: new UrlMapping(as: 'larp'), onUpdated: 'normalizeFilters')]
    public ?string $larpFilter = null;

    /** @var list<Character>|null */
    private ?array $characters = null;

    public function __construct(
        private readonly LarpRepository $larps,
        private readonly CharacterRepository $characterRepository,
        private readonly CharacterMatchCalculator $calculator,
    ) {
    }

    /**
     * Sets one gameplay rating. An action rather than data-model binding, because a
     * model path like "ratings.137" must already exist, and pre-filling every tag
     * with 0 would put all of them into the URL. Neutral (0) ratings are removed.
     */
    #[LiveAction]
    public function rate(#[LiveArg] int $tag, #[LiveArg] int $value): void
    {
        $ratings = $this->getRatingsById();
        $value = CharacterMatchCalculator::clamp($value);
        if (0 === $value) {
            unset($ratings[$tag]);
        } else {
            $ratings[$tag] = $value;
        }
        $this->ratings = $ratings;
    }

    #[LiveAction]
    public function reset(): void
    {
        $this->ratings = [];
        $this->triggers = [];
        $this->hideTriggers = null;
        $this->casting = null;
        $this->onlyAvailable = null;
        $this->larpFilter = null;
    }

    public function normalizeFilters(): void
    {
        $this->hideTriggers = $this->hideTriggers ?: null;
        $this->onlyAvailable = $this->onlyAvailable ?: null;
        $this->casting = $this->casting ?: null;
        $this->larpFilter = $this->larpFilter ?: null;
    }

    /** @return list<MatchResult> */
    public function getResults(): array
    {
        $characters = array_values(array_filter($this->getCharacters(), function (Character $c): bool {
            if (null !== $this->larpFilter && $c->getLarp()?->getSlug() !== $this->larpFilter) {
                return false;
            }
            if (null !== $this->casting && $c->getCasting()->value !== $this->casting && CharacterCasting::Any !== $c->getCasting()) {
                return false;
            }

            return !$this->onlyAvailable || CharacterAvailability::Available === $c->getAvailability();
        }));

        return $this->calculator->rank($characters, $this->getRatingsById(), $this->getTriggerIds(), (bool) $this->hideTriggers);
    }

    /**
     * Gameplay tags used by characters in scope, grouped by category.
     *
     * @return array<string, list<Tag>>
     */
    public function getGameplayGroups(): array
    {
        return $this->groupTags(TagType::Gameplay);
    }

    /** @return array<string, list<Tag>> */
    public function getTriggerGroups(): array
    {
        return $this->groupTags(TagType::Trigger);
    }

    public function getRating(Tag $tag): int
    {
        return $this->getRatingsById()[(int) $tag->getId()] ?? 0;
    }

    public function isTriggerSelected(Tag $tag): bool
    {
        return \in_array((int) $tag->getId(), $this->getTriggerIds(), true);
    }

    /** @return list<int> */
    public function getRatingScale(): array
    {
        return range(CharacterMatchCalculator::MIN_RATING, CharacterMatchCalculator::MAX_RATING);
    }

    /** @return list<CharacterCasting> */
    public function getCastingOptions(): array
    {
        return [CharacterCasting::Female, CharacterCasting::Male, CharacterCasting::NonBinary];
    }

    /** @return list<Larp> LARPs offered in the LARP filter (only on the all-LARPs page) */
    public function getLarpOptions(): array
    {
        if (null !== $this->larpSlug) {
            return [];
        }
        $larps = [];
        foreach ($this->getCharacters() as $character) {
            $larp = $character->getLarp();
            if (null !== $larp) {
                $larps[$larp->getSlug()] = $larp;
            }
        }
        ksort($larps);

        return array_values($larps);
    }

    public function getSelectedCount(): int
    {
        return \count(array_filter($this->getRatingsById())) + \count($this->getTriggerIds());
    }

    public function hasCharacters(): bool
    {
        return [] !== $this->getCharacters();
    }

    /** @return array<int, int> */
    private function getRatingsById(): array
    {
        $ratings = [];
        foreach ($this->ratings as $tagId => $rating) {
            if (is_numeric($tagId) && is_numeric($rating)) {
                $ratings[(int) $tagId] = CharacterMatchCalculator::clamp((int) $rating);
            }
        }

        return $ratings;
    }

    /** @return list<int> */
    private function getTriggerIds(): array
    {
        return array_values(array_map('intval', array_filter($this->triggers, 'is_numeric')));
    }

    /** @return array<string, list<Tag>> */
    private function groupTags(TagType $type): array
    {
        $tags = [];
        foreach ($this->getCharacters() as $character) {
            foreach ($character->getTagsOfType($type) as $characterTag) {
                $tag = $characterTag->getTag();
                if (null !== $tag) {
                    $tags[(int) $tag->getId()] = $tag;
                }
            }
        }

        usort($tags, static fn (Tag $a, Tag $b): int => [$a->getCategory()?->getPosition() ?? \PHP_INT_MAX, $a->getCategory()?->getName(), $a->getName()]
            <=> [$b->getCategory()?->getPosition() ?? \PHP_INT_MAX, $b->getCategory()?->getName(), $b->getName()]);

        $groups = [];
        foreach ($tags as $tag) {
            // A LARP's own tags without a category are grouped under the LARP's name.
            $groups[$tag->getCategory()?->getName() ?? $tag->getLarp()?->getName() ?? ''][] = $tag;
        }

        return $groups;
    }

    /** @return list<Character> */
    private function getCharacters(): array
    {
        if (null !== $this->characters) {
            return $this->characters;
        }

        if (null !== $this->larpSlug) {
            $larp = $this->larps->findPublishedBySlug($this->larpSlug);
            $larps = null === $larp ? [] : [$larp];
        } else {
            $larps = array_filter(
                $this->larps->findPublishedSections()['current'],
                static fn (Larp $l): bool => LarpStatus::Current === $l->getStatus(),
            );
        }

        $editions = [];
        foreach ($larps as $larp) {
            $edition = $larp->getCurrentEdition();
            if (null !== $edition && !$larp->isInitiative()) {
                $editions[] = $edition;
            }
        }

        return $this->characters = $this->characterRepository->findVisibleForEditions($editions);
    }
}
