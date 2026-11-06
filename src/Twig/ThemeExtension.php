<?php

declare(strict_types=1);

namespace App\Twig;

use App\Theme\Theme;
use App\Theme\ThemeManager;
use Twig\Attribute\AsTwigFunction;

/**
 * Twig functions for theme templates:
 *   {% for path in theme_stylesheets() %}<link rel="stylesheet" href="{{ asset(path) }}">{% endfor %}
 *   {{ asset(theme_asset('images/hero.svg')) }}
 */
final class ThemeExtension
{
    public function __construct(private readonly ThemeManager $themes)
    {
    }

    #[AsTwigFunction('theme')]
    public function theme(): Theme
    {
        return $this->themes->getActive();
    }

    /** @return list<string> */
    #[AsTwigFunction('theme_stylesheets')]
    public function stylesheets(): array
    {
        return $this->themes->getStylesheets();
    }

    /** @return list<string> */
    #[AsTwigFunction('theme_scripts')]
    public function scripts(): array
    {
        return $this->themes->getScripts();
    }

    #[AsTwigFunction('theme_asset')]
    public function asset(string $relativePath): ?string
    {
        return $this->themes->findAsset($relativePath);
    }
}
