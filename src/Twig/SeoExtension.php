<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Character;
use App\Entity\Gallery;
use App\Entity\Larp;
use App\Entity\LarpEdition;
use App\Seo\SeoUrls;
use App\Seo\StructuredData;
use App\Seo\Text;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

/**
 * SEO helpers for templates. A page describes itself with a top-level
 *   {% set seo = {description: …, image: …, type: 'article', jsonld: [...], noindex: true} %}
 * and partials/_seo.html.twig turns it into meta tags, Open Graph and JSON-LD.
 */
final class SeoExtension
{
    public function __construct(
        private readonly StructuredData $data,
        private readonly SeoUrls $urls,
    ) {
    }

    /** {{ html|meta_text }}: plain one-line text, cut to 160 characters. */
    #[AsTwigFilter('meta_text')]
    public function metaText(?string $html, int $max = 160): ?string
    {
        return Text::meta($html, $max);
    }

    /**
     * <script type="application/ld+json"> for one or more schema.org items; nulls
     * are skipped.
     *
     * @param array<mixed>|null $data
     */
    #[AsTwigFunction('json_ld', isSafe: ['html'])]
    public function jsonLd(?array $data): string
    {
        if (null === $data || [] === $data) {
            return '';
        }
        $items = array_is_list($data) ? array_values(array_filter($data)) : [$data];
        $out = '';
        foreach ($items as $item) {
            $out .= '<script type="application/ld+json">'.json_encode($item, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_THROW_ON_ERROR).'</script>'."\n";
        }

        return $out;
    }

    /** The object behind the {{ ld_* }} functions: ld().organization(settings), ld().event(edition, settings), … */
    #[AsTwigFunction('ld')]
    public function ld(): StructuredData
    {
        return $this->data;
    }

    /** Absolute share image for a LARP / edition / character / gallery, else the theme default. */
    #[AsTwigFunction('seo_image')]
    public function image(Larp|LarpEdition|Character|Gallery|null $subject = null): ?string
    {
        $image = match (true) {
            $subject instanceof LarpEdition => null !== $subject->getLarp() ? $this->urls->imageFor($subject->getLarp(), $subject) : null,
            $subject instanceof Larp => $this->urls->imageFor($subject),
            $subject instanceof Character => $this->urls->characterImage($subject),
            $subject instanceof Gallery => $this->urls->galleryImage($subject),
            default => null,
        };

        return $image ?? $this->urls->defaultImage();
    }

    /** {{ absolute_asset('uploads/x.jpg') }} */
    #[AsTwigFunction('absolute_asset')]
    public function absoluteAsset(string $path): string
    {
        return $this->urls->absolute($path);
    }
}
