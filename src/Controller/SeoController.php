<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Page;
use App\Repository\CharacterRepository;
use App\Repository\GalleryRepository;
use App\Repository\LarpRepository;
use App\Repository\PageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * sitemap.xml (every public page, for search engines) and robots.txt (keeps
 * crawlers out of the admin and points them to the sitemap).
 */
final class SeoController extends AbstractController
{
    #[Route('/sitemap.xml', name: 'app_sitemap', methods: ['GET'], priority: 10)]
    public function sitemap(PageRepository $pages, LarpRepository $larps, CharacterRepository $characters, GalleryRepository $galleries): Response
    {
        /** @var list<array{loc: string, lastmod: ?\DateTimeImmutable}> $urls */
        $urls = [];
        /** @param array<string, string|null> $params */
        $add = function (string $route, array $params = [], ?\DateTimeImmutable $lastmod = null) use (&$urls): void {
            $urls[] = ['loc' => $this->generateUrl($route, $params, UrlGeneratorInterface::ABSOLUTE_URL), 'lastmod' => $lastmod];
        };

        $add('app_home', [], $pages->findHome()?->getUpdatedAt());
        foreach ($pages->findBy(['published' => true, 'isHome' => false], ['slug' => 'ASC']) as $page) {
            if (null !== $page->getSlug() && !\in_array($page->getSlug(), Page::RESERVED_SLUGS, true)) {
                $add('app_page', ['slug' => $page->getSlug()], $page->getUpdatedAt());
            }
        }

        $add('app_larp_index');
        $add('app_characters');
        $editions = [];
        foreach ($larps->findPublishedSections() as $section) {
            foreach ($section as $larp) {
                $add('app_larp_show', ['slug' => $larp->getSlug()], $larp->getUpdatedAt());
                foreach ($larp->getSortedEditions() as $edition) {
                    $add('app_edition_show', ['slug' => $larp->getSlug(), 'edition' => $edition->getSlug()], $edition->getUpdatedAt());
                    $editions[] = $edition;
                }
            }
        }

        $withCharacters = [];
        foreach ($characters->findVisibleForEditions($editions) as $character) {
            $edition = $character->getEdition();
            $larp = $character->getLarp();
            if (null === $edition || null === $larp) {
                continue;
            }
            $params = ['slug' => $larp->getSlug(), 'edition' => $edition->getSlug()];
            if (!isset($withCharacters[spl_object_id($edition)])) {
                $withCharacters[spl_object_id($edition)] = true;
                $add('app_edition_characters', $params);
            }
            $add('app_edition_character', $params + ['characterSlug' => $character->getSlug()], $character->getUpdatedAt());
        }

        $add('app_gallery_index');
        foreach ($galleries->findVisible() as $gallery) {
            // Link-only galleries redirect to another site: nothing to index here.
            if (!$gallery->isLinkOnly()) {
                $add('app_gallery_show', ['slug' => $gallery->getSlug()], $gallery->getUpdatedAt());
            }
        }

        $response = $this->render('seo/sitemap.xml.twig', ['urls' => $urls]);
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');

        return $response;
    }

    #[Route('/robots.txt', name: 'app_robots', methods: ['GET'], priority: 10)]
    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /login',
            'Disallow: /_components/',
            '',
            'Sitemap: '.$this->generateUrl('app_sitemap', [], UrlGeneratorInterface::ABSOLUTE_URL),
            '',
        ];

        return new Response(implode("\n", $lines), Response::HTTP_OK, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
