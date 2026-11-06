<?php

declare(strict_types=1);

namespace App\Menu;

use App\Entity\MenuItem;
use App\Enum\MenuTarget;
use App\Repository\MenuRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Attribute\AsTwigFunction;

/**
 * {% for link in menu('main') %} … {% endfor %}.
 *
 * Builds the links of a menu: hides disabled items and items whose page or LARP is
 * unpublished or gone, and marks the item for the current page as active.
 */
final class MenuBuilder
{
    /** @var array<string, list<MenuLink>> */
    private array $cache = [];

    public function __construct(
        private readonly MenuRepository $menus,
        private readonly UrlGeneratorInterface $urls,
        private readonly RequestStack $requests,
    ) {
    }

    /** @return list<MenuLink> */
    #[AsTwigFunction('menu')]
    public function build(string $key): array
    {
        $path = $this->requests->getCurrentRequest()?->getPathInfo() ?? '';
        $cacheKey = $key.'|'.$path;
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $menu = $this->menus->findOneBy(['menuKey' => $key]);
        $links = [];
        foreach ($menu?->getRootItems() ?? [] as $item) {
            $link = $this->link($item, $path, true);
            if (null !== $link) {
                $links[] = $link;
            }
        }

        return $this->cache[$cacheKey] = $links;
    }

    private function link(MenuItem $item, string $currentPath, bool $withChildren): ?MenuLink
    {
        if (!$item->isEnabled()) {
            return null;
        }

        $children = [];
        if ($withChildren) {
            foreach ($item->getChildren() as $child) {
                $link = $this->link($child, $currentPath, false);
                if (null !== $link) {
                    $children[] = $link;
                }
            }
        }

        $url = $this->url($item);
        if (null === $url && MenuTarget::None !== $item->getTarget()) {
            return null; // target unpublished or deleted
        }
        if (null === $url && [] === $children) {
            return null; // an empty dropdown
        }

        $active = null !== $url && $this->isCurrent($url, $currentPath);
        foreach ($children as $child) {
            $active = $active || $child->active;
        }

        return new MenuLink($item->getLabel(), $url, $item->isNewTab(), $active, $children);
    }

    private function url(MenuItem $item): ?string
    {
        return match ($item->getTarget()) {
            MenuTarget::Page => match (true) {
                null === $item->getPage() || !$item->getPage()->isPublished() => null,
                $item->getPage()->isHome() => $this->urls->generate('app_home'),
                default => $this->urls->generate('app_page', ['slug' => $item->getPage()->getSlug()]),
            },
            MenuTarget::Larp => null !== $item->getLarp() && $item->getLarp()->isPublished()
                ? $this->urls->generate('app_larp_show', ['slug' => $item->getLarp()->getSlug()])
                : null,
            MenuTarget::Route => isset(MenuItem::ROUTES[(string) $item->getRoute()]) ? $this->urls->generate((string) $item->getRoute()) : null,
            MenuTarget::Url => $item->getUrl(),
            MenuTarget::None => null,
        };
    }

    private function isCurrent(string $url, string $currentPath): bool
    {
        $path = parse_url($url, \PHP_URL_PATH);
        if (!\is_string($path) || str_starts_with($url, 'http')) {
            return false;
        }

        // "/larp/x" is also active on "/larp/x/postacie"; "/" only on the front page itself.
        return '/' === $path ? '/' === $currentPath : ($currentPath === $path || str_starts_with($currentPath, rtrim($path, '/').'/'));
    }
}
