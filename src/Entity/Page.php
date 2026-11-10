<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PageRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * A content page made of blocks (the front page is the one with isHome = true).
 */
#[ORM\Entity(repositoryClass: PageRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['slug'])]
class Page implements SluggableInterface, \Stringable
{
    use TimestampableTrait;

    /** Slugs that would clash with the site's own URLs. */
    public const RESERVED_SLUGS = ['admin', 'login', 'logout', 'larpy', 'larp', 'postacie', 'galerie', 'galeria', 'assets', 'uploads', 'bundles', '_profiler', '_wdt'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 160)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 160)]
    private string $title = '';

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\Regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', message: 'slug.invalid')]
    private ?string $slug = null;

    #[ORM\Column]
    private bool $isHome = false;

    #[ORM\Column]
    private bool $published = true;

    #[ORM\Column(length: 300, nullable: true)]
    #[Assert\Length(max: 300)]
    private ?string $metaDescription = null;

    /** @var Collection<int, PageBlock> */
    #[ORM\OneToMany(targetEntity: PageBlock::class, mappedBy: 'page', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $blocks;

    public function __construct()
    {
        $this->blocks = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->title;
    }

    #[Assert\Callback]
    public function validateSlug(ExecutionContextInterface $context): void
    {
        if (\in_array($this->slug, self::RESERVED_SLUGS, true)) {
            $context->buildViolation('page.reserved_slug')->atPath('slug')->addViolation();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

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
        return $this->title;
    }

    public function isHome(): bool
    {
        return $this->isHome;
    }

    public function setIsHome(bool $isHome): static
    {
        $this->isHome = $isHome;

        return $this;
    }

    public function isPublished(): bool
    {
        return $this->published;
    }

    public function setPublished(bool $published): static
    {
        $this->published = $published;

        return $this;
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function setMetaDescription(?string $metaDescription): static
    {
        $this->metaDescription = $metaDescription;

        return $this;
    }

    /** @return Collection<int, PageBlock> */
    public function getBlocks(): Collection
    {
        return $this->blocks;
    }

    /** @return list<PageBlock> */
    public function getEnabledBlocks(): array
    {
        return array_values($this->blocks->filter(static fn (PageBlock $b): bool => $b->isEnabled())->toArray());
    }

    public function addBlock(PageBlock $block): static
    {
        if (!$this->blocks->contains($block)) {
            $this->blocks->add($block);
            $block->setPage($this);
        }

        return $this;
    }

    public function removeBlock(PageBlock $block): static
    {
        $this->blocks->removeElement($block);

        return $this;
    }

    public function getBlockCount(): int
    {
        return $this->blocks->count();
    }
}
