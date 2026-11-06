<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Entities with a URL slug. An empty slug is generated from getSlugSource()
 * on save (see App\Doctrine\SlugListener).
 */
interface SluggableInterface
{
    public function getSlug(): ?string;

    public function setSlug(?string $slug): static;

    public function getSlugSource(): string;
}
