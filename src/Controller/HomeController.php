<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\LarpRepository;
use App\Repository\SiteSettingsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Temporary public home page. It is replaced by the block-based front page
 * (page builder) and the theme system in later issues.
 */
class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(SiteSettingsRepository $settings, LarpRepository $larps): Response
    {
        return $this->render('site/home.html.twig', [
            'settings' => $settings->get(),
            'current' => $larps->findPublishedSections()['current'],
        ]);
    }
}
