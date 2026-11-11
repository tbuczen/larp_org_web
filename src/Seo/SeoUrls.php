<?php

declare(strict_types=1);

namespace App\Seo;

use App\Entity\Character;
use App\Entity\Gallery;
use App\Entity\Larp;
use App\Entity\LarpEdition;
use App\Theme\ThemeManager;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Absolute image URLs for sharing previews (og:image) and structured data:
 * the page's own picture, else the theme's default share image.
 */
final class SeoUrls
{
    /** Raster share image a theme can provide (1200×630; Facebook ignores SVG). */
    public const DEFAULT_IMAGE = 'images/og.png';

    public function __construct(
        private readonly Packages $packages,
        private readonly RequestStack $requests,
        private readonly UrlGeneratorInterface $urls,
        private readonly ThemeManager $themes,
    ) {
    }

    public function absolute(string $path): string
    {
        $url = $this->packages->getUrl($path);
        if (preg_match('#^https?://#', $url)) {
            return $url;
        }
        $request = $this->requests->getMainRequest();
        $base = null !== $request
            ? $request->getSchemeAndHttpHost()
            : rtrim($this->urls->generate('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL), '/');

        return $base.$url;
    }

    public function defaultImage(): ?string
    {
        $path = $this->themes->findAsset(self::DEFAULT_IMAGE);

        return null === $path ? null : $this->absolute($path);
    }

    /** The edition's cover, else the LARP's. */
    public function imageFor(Larp $larp, ?LarpEdition $edition = null): ?string
    {
        if (null !== $edition && null !== $edition->getCoverImage()) {
            return $this->absolute('uploads/editions/'.$edition->getCoverImage());
        }
        if (null !== $larp->getCoverImage()) {
            return $this->absolute('uploads/larps/'.$larp->getCoverImage());
        }

        return null;
    }

    public function characterImage(Character $character): ?string
    {
        if (null !== $character->getPortrait()) {
            return $this->absolute('uploads/characters/'.$character->getPortrait());
        }
        $larp = $character->getLarp();

        return null === $larp ? null : $this->imageFor($larp, $character->getEdition());
    }

    public function galleryImage(Gallery $gallery): ?string
    {
        $cover = $gallery->getCoverPath();
        if (null !== $cover) {
            return $this->absolute('uploads/galleries/'.$cover);
        }
        $larp = $gallery->getLarp();

        return null === $larp ? null : $this->imageFor($larp, $gallery->getEdition());
    }
}
