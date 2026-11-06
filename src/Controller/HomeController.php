<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\LarpRepository;
use App\Repository\PageRepository;
use App\Repository\SiteSettingsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(SiteSettingsRepository $settings, PageRepository $pages, LarpRepository $larps): Response
    {
        $home = $pages->findHome();

        // Until a front page has been built in the admin, show a simple fallback.
        if (null === $home) {
            return $this->render('site/home.html.twig', [
                'settings' => $settings->get(),
                'current' => $larps->findPublishedSections()['current'],
            ]);
        }

        return $this->render('page/show.html.twig', [
            'settings' => $settings->get(),
            'page' => $home,
        ]);
    }
}
