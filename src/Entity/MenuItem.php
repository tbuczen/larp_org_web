<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\MenuTarget;
use App\Repository\MenuItemRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * One link in a menu. Top-level items can have one level of children (a dropdown).
 * Pages and LARPs are linked by entity, so changing their slug doesn't break the menu.
 */
#[ORM\Entity(repositoryClass: MenuItemRepository::class)]
class MenuItem implements \Stringable
{
    /** Built-in pages a menu item can link to: route name => translation key. */
    public const ROUTES = [
        'app_home' => 'menu_route.home',
        'app_larp_index' => 'menu_route.larps',
        'app_characters' => 'menu_route.characters',
        'app_gallery_index' => 'menu_route.galleries',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Menu $menu = null;

    #[ORM\ManyToOne(inversedBy: 'children')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?MenuItem $parent = null;

    /** @var Collection<int, MenuItem> */
    #[ORM\OneToMany(targetEntity: MenuItem::class, mappedBy: 'parent')]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $children;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 80)]
    private string $label = '';

    #[ORM\Column(length: 10, enumType: MenuTarget::class)]
    private MenuTarget $target = MenuTarget::Page;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Page $page = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Larp $larp = null;

    #[ORM\Column(length: 60, nullable: true)]
    private ?string $route = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url(requireTld: true)]
    private ?string $url = null;

    #[ORM\Column]
    private bool $newTab = false;

    #[ORM\Column]
    private bool $enabled = true;

    #[ORM\Column]
    private int $position = 0;

    public function __construct()
    {
        $this->children = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->label;
    }

    #[Assert\Callback]
    public function validateTarget(ExecutionContextInterface $context): void
    {
        $missing = match ($this->target) {
            MenuTarget::Page => null === $this->page ? 'page' : null,
            MenuTarget::Larp => null === $this->larp ? 'larp' : null,
            MenuTarget::Route => !isset(self::ROUTES[(string) $this->route]) ? 'route' : null,
            MenuTarget::Url => null === $this->url || '' === $this->url ? 'url' : null,
            MenuTarget::None => null,
        };
        if (null !== $missing) {
            $context->buildViolation('menu_item.target_missing')->atPath($missing)->addViolation();
        }
        if (null !== $this->parent && null !== $this->parent->getParent()) {
            $context->buildViolation('menu_item.too_deep')->atPath('parent')->addViolation();
        }
        if (null !== $this->parent && $this->parent === $this) {
            $context->buildViolation('menu_item.too_deep')->atPath('parent')->addViolation();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMenu(): ?Menu
    {
        return $this->menu;
    }

    public function setMenu(?Menu $menu): static
    {
        $this->menu = $menu;

        return $this;
    }

    public function getParent(): ?MenuItem
    {
        return $this->parent;
    }

    public function setParent(?MenuItem $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    /** @return Collection<int, MenuItem> */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function addChild(MenuItem $child): static
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
            $child->setParent($this);
            $child->setMenu($this->menu);
        }

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getTarget(): MenuTarget
    {
        return $this->target;
    }

    public function setTarget(MenuTarget $target): static
    {
        $this->target = $target;

        return $this;
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

    public function getLarp(): ?Larp
    {
        return $this->larp;
    }

    public function setLarp(?Larp $larp): static
    {
        $this->larp = $larp;

        return $this;
    }

    public function getRoute(): ?string
    {
        return $this->route;
    }

    public function setRoute(?string $route): static
    {
        $this->route = $route;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function isNewTab(): bool
    {
        return $this->newTab;
    }

    public function setNewTab(bool $newTab): static
    {
        $this->newTab = $newTab;

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

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }
}
