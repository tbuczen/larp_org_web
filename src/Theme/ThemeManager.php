<?php

declare(strict_types=1);

namespace App\Theme;

use App\Repository\SiteSettingsRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Finds themes in themes/<name>/ and resolves the active theme (chosen in Site settings)
 * with its parents, e.g. lastajemnic → base.
 *
 * - Templates: looked up in the active theme first, then each parent, then templates/
 *   (see ThemeTemplateLoader).
 * - Styles and scripts: every theme in the chain contributes, parents first, so a child
 *   theme only adds or overrides what it needs.
 */
final class ThemeManager implements ResetInterface
{
    public const BASE = 'base';

    /** @var array<string, Theme>|null */
    private ?array $themes = null;

    /** @var list<Theme>|null */
    private ?array $chain = null;

    private ?string $override = null;

    public function __construct(
        #[Autowire('%kernel.project_dir%/themes')]
        private readonly string $themesDir,
        private readonly SiteSettingsRepository $settings,
    ) {
    }

    /** @return array<string, Theme> name => theme */
    public function all(): array
    {
        if (null !== $this->themes) {
            return $this->themes;
        }

        $this->themes = [];
        foreach (glob($this->themesDir.'/*/theme.yaml') ?: [] as $file) {
            $path = \dirname($file);
            $name = basename($path);
            /** @var array<string, mixed> $config */
            $config = Yaml::parseFile($file) ?? [];
            $this->themes[$name] = new Theme(
                name: $name,
                label: (string) ($config['name'] ?? $name),
                description: (string) ($config['description'] ?? ''),
                extends: isset($config['extends']) ? (string) $config['extends'] : (self::BASE === $name ? null : self::BASE),
                path: $path,
                styles: array_values(array_map('strval', (array) ($config['styles'] ?? ['tokens.css', 'base.css', 'components.css', 'effects.css']))),
                scripts: array_values(array_map('strval', (array) ($config['scripts'] ?? []))),
                preload: array_values(array_map('strval', (array) ($config['preload'] ?? []))),
            );
        }
        ksort($this->themes);

        return $this->themes;
    }

    /** Use another theme for the rest of this request (e.g. an admin preview). */
    public function useTheme(?string $name): void
    {
        $this->override = $name;
        $this->chain = null;
    }

    public function getActive(): Theme
    {
        return $this->getChain()[0];
    }

    /** @return list<Theme> active theme first, base last */
    public function getChain(): array
    {
        if (null !== $this->chain) {
            return $this->chain;
        }

        $themes = $this->all();
        $name = $this->override ?? $this->configuredThemeName();
        if (!isset($themes[$name])) {
            $name = self::BASE;
        }

        $chain = [];
        while (null !== $name && isset($themes[$name]) && !isset($chain[$name])) {
            $chain[$name] = $themes[$name];
            $name = $themes[$name]->extends;
        }

        return $this->chain = array_values($chain);
    }

    /** @return list<string> template folders, active theme first */
    public function getTemplateDirs(): array
    {
        return array_values(array_filter(
            array_map(static fn (Theme $t): string => $t->templatesDir(), $this->getChain()),
            'is_dir',
        ));
    }

    /** @return list<string> logical asset paths of the CSS files, base theme first */
    public function getStylesheets(): array
    {
        $paths = [];
        foreach (array_reverse($this->getChain()) as $theme) {
            foreach ($theme->styles as $file) {
                if ($theme->hasAsset('styles/'.$file)) {
                    $paths[] = $theme->assetPath('styles/'.$file);
                }
            }
        }

        return $paths;
    }

    /** @return list<string> logical asset paths of the fonts to preload, base theme first */
    public function getPreloads(): array
    {
        $paths = [];
        foreach (array_reverse($this->getChain()) as $theme) {
            foreach ($theme->preload as $file) {
                if ($theme->hasAsset($file)) {
                    $paths[] = $theme->assetPath($file);
                }
            }
        }

        return $paths;
    }

    /** @return list<string> logical asset paths of the JS modules, base theme first */
    public function getScripts(): array
    {
        $paths = [];
        foreach (array_reverse($this->getChain()) as $theme) {
            foreach ($theme->scripts as $file) {
                if ($theme->hasAsset('scripts/'.$file)) {
                    $paths[] = $theme->assetPath('scripts/'.$file);
                }
            }
        }

        return $paths;
    }

    /** First theme in the chain that has this asset, e.g. "images/hero.svg". */
    public function findAsset(string $relativePath): ?string
    {
        foreach ($this->getChain() as $theme) {
            if ($theme->hasAsset($relativePath)) {
                return $theme->assetPath($relativePath);
            }
        }

        return null;
    }

    public function reset(): void
    {
        $this->chain = null;
        $this->override = null;
    }

    private function configuredThemeName(): string
    {
        try {
            return $this->settings->findOneBy([], ['id' => 'ASC'])?->getTheme() ?? self::BASE;
        } catch (\Throwable) {
            // No database yet (fresh install, CLI linting): fall back to the base theme.
            return self::BASE;
        }
    }
}
