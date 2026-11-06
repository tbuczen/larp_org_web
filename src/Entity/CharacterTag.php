<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CharacterTagRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A tag on a character with a percentage: for triggers, how strongly or often
 * the content appears; for gameplay, how much of the character's play it is.
 */
#[ORM\Entity(repositoryClass: CharacterTagRepository::class)]
#[ORM\UniqueConstraint(fields: ['character', 'tag'])]
class CharacterTag implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'characterTags')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Character $character = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Tag $tag = null;

    #[ORM\Column]
    #[Assert\Range(min: 1, max: 100)]
    private int $percentage = 50;

    public function __construct(?Tag $tag = null, int $percentage = 50)
    {
        $this->tag = $tag;
        $this->percentage = $percentage;
    }

    public function __toString(): string
    {
        return sprintf('%s (%d%%)', $this->tag?->getName() ?? '?', $this->percentage);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCharacter(): ?Character
    {
        return $this->character;
    }

    public function setCharacter(?Character $character): static
    {
        $this->character = $character;

        return $this;
    }

    public function getTag(): ?Tag
    {
        return $this->tag;
    }

    public function setTag(?Tag $tag): static
    {
        $this->tag = $tag;

        return $this;
    }

    public function getPercentage(): int
    {
        return $this->percentage;
    }

    public function setPercentage(int $percentage): static
    {
        $this->percentage = $percentage;

        return $this;
    }
}
