<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\LarpEdition;
use App\Util\DateRangeFormatter;
use Twig\Attribute\AsTwigFilter;

final class AppExtension
{
    /** {{ edition|edition_dates }} → "6–9.VIII.2026" */
    #[AsTwigFilter('edition_dates')]
    public function editionDates(?LarpEdition $edition): ?string
    {
        return null === $edition ? null : DateRangeFormatter::format($edition->getStartDate(), $edition->getEndDate());
    }
}
