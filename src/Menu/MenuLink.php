<?php

declare(strict_types=1);

namespace App\Menu;

/**
 * A resolved menu entry, ready for templates.
 */
final readonly class MenuLink
{
    /** @param list<MenuLink> $children */
    public function __construct(
        public string $label,
        public ?string $url,
        public bool $newTab,
        public bool $active,
        public array $children = [],
    ) {
    }

    public function hasChildren(): bool
    {
        return [] !== $this->children;
    }
}
