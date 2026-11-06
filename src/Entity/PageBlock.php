<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PageBlockRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * One building block of a page. What it shows is decided by its type (a class in
 * src/Block/Type/) and its settings, stored as JSON.
 */
#[ORM\Entity(repositoryClass: PageBlockRepository::class)]
#[ORM\HasLifecycleCallbacks]
class PageBlock implements \Stringable
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'blocks')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Page $page = null;

    /** Block type key, e.g. "hero" (see App\Block\BlockTypeInterface::getKey()). */
    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    private string $type = '';

    /** Optional name shown only in the admin, to tell blocks apart. */
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $adminLabel = null;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column]
    private bool $enabled = true;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $settings = [];

    /** @param array<string, mixed> $settings */
    public function __construct(string $type = '', array $settings = [])
    {
        $this->type = $type;
        $this->settings = $settings;
    }

    public function __toString(): string
    {
        return $this->adminLabel ?: $this->type;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPage(): ?Page
    {
        return $this->page;
    }

    public function setPage(?Page $page): static
    {
        $this->page = $page;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getAdminLabel(): ?string
    {
        return $this->adminLabel;
    }

    public function setAdminLabel(?string $adminLabel): static
    {
        $this->adminLabel = $adminLabel;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getSettings(): array
    {
        return $this->settings;
    }

    /** @param array<string, mixed> $settings */
    public function setSettings(array $settings): static
    {
        $this->settings = $settings;

        return $this;
    }
}
