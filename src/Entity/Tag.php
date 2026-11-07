<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TagType;
use App\Repository\TagRepository;
use App\Validator\NotShadowingSharedTag;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * A trigger (sensitive content) or gameplay tag.
 *
 * Shared tags (no LARP) are used by every LARP, so a visitor's preferences apply
 * across games. A LARP can also define its own tags (larp set), offered only for
 * its characters.
 */
#[ORM\Entity(repositoryClass: TagRepository::class)]
#[ORM\UniqueConstraint(fields: ['type', 'slug', 'larp'])]
#[UniqueEntity(fields: ['type', 'slug', 'larp'], errorPath: 'name', message: 'tag.duplicate', ignoreNull: false)]
#[NotShadowingSharedTag]
class Tag implements SluggableInterface, \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    private string $name = '';

    #[ORM\Column(length: 140)]
    private ?string $slug = null;

    #[ORM\Column(length: 20, enumType: TagType::class)]
    private TagType $type = TagType::Trigger;

    #[ORM\ManyToOne(inversedBy: 'tags')]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?TagCategory $category = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /** Null: shared by all LARPs. Set: this LARP's own tag. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?Larp $larp = null;

    public function __toString(): string
    {
        return $this->name;
    }

    #[Assert\Callback]
    public function validateCategory(ExecutionContextInterface $context): void
    {
        if (null !== $this->category && $this->category->getType() !== $this->type) {
            $context->buildViolation('tag.category_type_mismatch')->atPath('category')->addViolation();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getSlugSource(): string
    {
        return $this->name;
    }

    public function getType(): TagType
    {
        return $this->type;
    }

    public function setType(TagType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function isTrigger(): bool
    {
        return TagType::Trigger === $this->type;
    }

    public function getCategory(): ?TagCategory
    {
        return $this->category;
    }

    public function setCategory(?TagCategory $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getLarp(): ?Larp
    {
        return $this->larp;
    }

    public function setLarp(?Larp $larp): static
    {
        $this->larp = $larp;

        return $this;
    }

    public function isShared(): bool
    {
        return null === $this->larp;
    }

    /** Whether this tag may be used for a character of the given LARP. */
    public function isAvailableFor(?Larp $larp): bool
    {
        return null === $this->larp || $this->larp === $larp;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }
}
