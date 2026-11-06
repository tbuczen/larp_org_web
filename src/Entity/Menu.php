<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MenuRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A navigation menu. Themes render it by key: {% for item in menu('main') %}.
 */
#[ORM\Entity(repositoryClass: MenuRepository::class)]
#[UniqueEntity(fields: ['menuKey'])]
class Menu implements \Stringable
{
    public const MAIN = 'main';
    public const FOOTER = 'footer';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Used by templates, e.g. "main", "footer". */
    #[ORM\Column(length: 40, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Regex('/^[a-z0-9_-]+$/')]
    private string $menuKey = '';

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    private string $name = '';

    /** @var Collection<int, MenuItem> */
    #[ORM\OneToMany(targetEntity: MenuItem::class, mappedBy: 'menu', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $items;

    public function __construct(string $menuKey = '', string $name = '')
    {
        $this->menuKey = $menuKey;
        $this->name = $name;
        $this->items = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->name;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMenuKey(): string
    {
        return $this->menuKey;
    }

    public function setMenuKey(string $menuKey): static
    {
        $this->menuKey = $menuKey;

        return $this;
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

    /** @return Collection<int, MenuItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    /** @return list<MenuItem> top-level items in order */
    public function getRootItems(): array
    {
        return array_values($this->items->filter(static fn (MenuItem $i): bool => null === $i->getParent())->toArray());
    }

    public function addItem(MenuItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setMenu($this);
        }

        return $this;
    }

    public function removeItem(MenuItem $item): static
    {
        $this->items->removeElement($item);

        return $this;
    }

    public function getItemCount(): int
    {
        return $this->items->count();
    }
}
