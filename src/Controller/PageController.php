<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Page;
use App\Repository\PageRepository;
use App\Repository\SiteSettingsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Block-based content pages, e.g. /stowarzyszenie or /kontakt. Lowest priority, so
 * the site's own routes always win.
 */
class PageController extends AbstractController
{
    public function __construct(
        private readonly PageRepository $pages,
        private readonly SiteSettingsRepository $settings,
    ) {
    }

    #[Route('/{slug}', name: 'app_page', requirements: ['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'], priority: -100)]
    public function show(string $slug): Response
    {
        $page = $this->pages->findPublishedBySlug($slug);
        if (null === $page || $page->isHome() || \in_array($slug, Page::RESERVED_SLUGS, true)) {
            throw new NotFoundHttpException();
        }

        return $this->render('page/show.html.twig', [
            'settings' => $this->settings->get(),
            'page' => $page,
        ]);
    }
}
