<?php

declare(strict_types=1);

namespace App\Block;

use App\Entity\Larp;
use App\Enum\LarpStatus;
use App\Repository\LarpRepository;

/**
 * For blocks that point at a LARP. Settings are JSON, so the LARP is stored by id.
 */
trait LarpChoicesTrait
{
    public const AUTO = 'auto';

    /** @return array<string, string> label => value ('auto' or a LARP id) */
    private function larpChoices(LarpRepository $larps, string $autoLabel): array
    {
        $choices = [$autoLabel => self::AUTO];
        foreach ($larps->findBy([], ['name' => 'ASC']) as $larp) {
            if (LarpStatus::Draft !== $larp->getStatus()) {
                $choices[$larp->getName()] = (string) $larp->getId();
            }
        }

        return $choices;
    }

    /**
     * The chosen LARP, or for "auto": the featured current LARP, else the one whose
     * edition comes next.
     */
    private function resolveLarp(LarpRepository $larps, mixed $value): ?Larp
    {
        if (is_numeric($value)) {
            $larp = $larps->find((int) $value);

            return null !== $larp && $larp->isPublished() ? $larp : null;
        }

        $current = array_filter($larps->findPublishedSections()['current'], static fn (Larp $l): bool => !$l->isInitiative());
        foreach ($current as $larp) {
            if ($larp->isFeatured()) {
                return $larp;
            }
        }
        usort($current, static fn (Larp $a, Larp $b): int => [$a->getCurrentEdition()?->getStartDate() ?? new \DateTimeImmutable('+100 years')] <=> [$b->getCurrentEdition()?->getStartDate() ?? new \DateTimeImmutable('+100 years')]);

        return $current[0] ?? null;
    }
}
