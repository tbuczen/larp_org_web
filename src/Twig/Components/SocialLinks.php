<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Social\SocialLink;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Social media buttons: <twig:SocialLinks :links="larp.socialButtons" />
 * Renders nothing when there are no links. Template: components/SocialLinks.html.twig
 * (themes can override it).
 */
#[AsTwigComponent]
final class SocialLinks
{
    /** @var list<SocialLink> */
    public array $links = [];

    /** Accessible name of the list; null = "Media społecznościowe". */
    public ?string $label = null;

    /** Extra class, e.g. "social-links--compact". */
    public string $variant = '';
}
