<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TagType;
use App\Repository\TagCategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Groups tags in the admin and in the visitor filter, e.g. "Śmierć i żałoba".
 */
#[ORM\Entity(repositoryClass: TagCategoryRepository::class)]
class TagCategory implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    private string $name = '';

    #[ORM\Column(length: 20, enumType: TagType::class)]
    private TagType $type = TagType::Trigger;

    #[ORM\Column]
    private int $position = 0;

    /** @var Collection<int, Tag> */
    #[ORM\OneToMany(targetEntity: Tag::class, mappedBy: 'category')]
    #[ORM\OrderBy(['name' => 'ASC'])]
    private Collection $tags;

    public function __construct()
    {
        $this->tags = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->name;
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

    public function getType(): TagType
    {
        return $this->type;
    }

    public function setType(TagType $type): static
    {
        $this->type = $type;

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

    /** @return Collection<int, Tag> */
    public function getTags(): Collection
    {
        return $this->tags;
    }
}
