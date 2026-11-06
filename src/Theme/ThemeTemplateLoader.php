<?php

declare(strict_types=1);

namespace App\Theme;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Twig\Error\LoaderError;
use Twig\Loader\FilesystemLoader;
use Twig\Loader\LoaderInterface;
use Twig\Source;

/**
 * Lets the active theme (and its parents) override any template by placing a file
 * with the same name in themes/<name>/templates/. Templates it doesn't have fall
 * through to the next loader (templates/ and bundles).
 */
#[AutoconfigureTag('twig.loader', ['priority' => 100])]
final class ThemeTemplateLoader implements LoaderInterface
{
    /** @var array<string, FilesystemLoader> one loader per theme chain */
    private array $loaders = [];

    public function __construct(private readonly ThemeManager $themes)
    {
    }

    public function getSourceContext(string $name): Source
    {
        return $this->loader()->getSourceContext($name);
    }

    public function getCacheKey(string $name): string
    {
        return $this->loader()->getCacheKey($name);
    }

    public function isFresh(string $name, int $time): bool
    {
        return $this->loader()->isFresh($name, $time);
    }

    public function exists(string $name): bool
    {
        // Namespaced templates (@EasyAdmin/..., @Twig/...) are never themed.
        if (str_starts_with($name, '@')) {
            return false;
        }

        try {
            return $this->loader()->exists($name);
        } catch (LoaderError) {
            return false;
        }
    }

    private function loader(): FilesystemLoader
    {
        $dirs = $this->themes->getTemplateDirs();
        $key = implode('|', $dirs);

        return $this->loaders[$key] ??= new FilesystemLoader($dirs);
    }
}
