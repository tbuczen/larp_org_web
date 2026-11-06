<?php

declare(strict_types=1);

namespace App\Theme;

/**
 * One theme folder: themes/<name>/ with theme.yaml, templates/ and assets/.
 */
final readonly class Theme
{
    /**
     * @param list<string> $styles  CSS files in assets/styles/, loaded in this order
     * @param list<string> $scripts JS modules in assets/scripts/, loaded as <script type="module">
     */
    public function __construct(
        public string $name,
        public string $label,
        public string $description,
        public ?string $extends,
        public string $path,
        public array $styles,
        public array $scripts,
    ) {
    }

    public function templatesDir(): string
    {
        return $this->path.'/templates';
    }

    public function hasAsset(string $relativePath): bool
    {
        return is_file($this->path.'/assets/'.$relativePath);
    }

    /** Logical AssetMapper path of a file in this theme's assets/ folder. */
    public function assetPath(string $relativePath): string
    {
        return 'themes/'.$this->name.'/assets/'.$relativePath;
    }
}
