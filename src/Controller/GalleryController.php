<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\GalleryRepository;
use App\Repository\SiteSettingsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class GalleryController extends AbstractController
{
    public function __construct(
        private readonly GalleryRepository $galleries,
        private readonly SiteSettingsRepository $settings,
    ) {
    }

    #[Route('/galerie', name: 'app_gallery_index')]
    public function index(): Response
    {
        return $this->render('gallery/index.html.twig', [
            'settings' => $this->settings->get(),
            'galleries' => $this->galleries->findVisible(),
        ]);
    }

    #[Route('/galeria/{slug}', name: 'app_gallery_show')]
    public function show(string $slug): Response
    {
        $gallery = $this->galleries->findVisibleBySlug($slug) ?? throw new NotFoundHttpException();
        // A gallery that is only a link to an album elsewhere: go there.
        if ($gallery->isLinkOnly()) {
            return $this->redirect((string) $gallery->getExternalUrl());
        }

        return $this->render('gallery/show.html.twig', [
            'settings' => $this->settings->get(),
            'gallery' => $gallery,
            'photos' => $gallery->getPhotos()->toArray(),
        ]);
    }
}
