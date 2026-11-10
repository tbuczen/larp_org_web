<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Larp;
use App\Entity\LarpEdition;
use App\Theme\LarpColors;
use App\Util\DateRangeFormatter;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

final class AppExtension
{
    /** {{ edition|edition_dates }} → "6–9.VIII.2026" */
    #[AsTwigFilter('edition_dates')]
    public function editionDates(?LarpEdition $edition): ?string
    {
        return null === $edition ? null : DateRangeFormatter::format($edition->getStartDate(), $edition->getEndDate());
    }

    /** {{ date|short_date }} → "8.VIII.2026", the same style as edition dates */
    #[AsTwigFilter('short_date')]
    public function shortDate(?\DateTimeInterface $date): ?string
    {
        return DateRangeFormatter::format($date);
    }

    /**
     * {{ larp_colors(larp) }} → "--color-bg: #120d0b; --color-text: …" (empty when
     * the LARP keeps the theme's colours). Used in partials/_larp_colors.html.twig
     * and on LARP cards.
     */
    #[AsTwigFunction('larp_colors')]
    public function larpColors(?Larp $larp): string
    {
        return null === $larp ? '' : LarpColors::css($larp);
    }
}
