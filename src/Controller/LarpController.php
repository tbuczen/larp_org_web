<?php

declare(strict_types=1);

namespace App\Controller;

use App\Calendar\IcsCalendar;
use App\Entity\Character;
use App\Entity\Larp;
use App\Repository\CharacterRepository;
use App\Repository\GalleryRepository;
use App\Repository\LarpRepository;
use App\Repository\SiteSettingsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class LarpController extends AbstractController
{
    public function __construct(
        private readonly LarpRepository $larps,
        private readonly CharacterRepository $characters,
        private readonly SiteSettingsRepository $settings,
        private readonly GalleryRepository $galleries,
    ) {
    }

    #[Route('/larpy', name: 'app_larp_index')]
    public function index(): Response
    {
        return $this->render('larp/index.html.twig', [
            'settings' => $this->settings->get(),
            'sections' => $this->larps->findPublishedSections(),
        ]);
    }

    #[Route('/larp/{slug}', name: 'app_larp_show')]
    public function show(string $slug): Response
    {
        $larp = $this->findLarp($slug);
        $edition = $larp->getCurrentEdition();

        return $this->render('larp/show.html.twig', [
            'settings' => $this->settings->get(),
            'larp' => $larp,
            'edition' => $edition,
            'characters' => null === $edition ? [] : $this->characters->findVisibleForEdition($edition),
            'galleries' => $this->galleries->findVisible($larp),
        ]);
    }

    /** The current edition as an .ics file ("add to calendar"). */
    #[Route('/larp/{slug}/kalendarz.ics', name: 'app_larp_calendar')]
    public function calendar(string $slug): Response
    {
        $larp = $this->findLarp($slug);
        $edition = $larp->isInitiative() ? null : $larp->getCurrentEdition();
        $ics = null === $edition ? null : IcsCalendar::forEdition(
            $edition,
            $this->generateUrl('app_larp_show', ['slug' => $larp->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
            $this->settings->get()->getOrganizationName(),
        );
        if (null === $ics) {
            throw new NotFoundHttpException();
        }

        return new Response($ics, Response::HTTP_OK, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $slug.'.ics'),
        ]);
    }

    /** Character matcher across all currently organized LARPs. */
    #[Route('/postacie', name: 'app_characters')]
    public function allCharacters(): Response
    {
        return $this->render('character/matcher.html.twig', [
            'settings' => $this->settings->get(),
        ]);
    }

    /** Character matcher for one LARP (its current edition). */
    #[Route('/larp/{slug}/postacie', name: 'app_larp_characters')]
    public function characters(string $slug): Response
    {
        $larp = $this->findLarp($slug);
        $edition = $larp->getCurrentEdition();
        if (null === $edition || $larp->isInitiative()) {
            throw new NotFoundHttpException();
        }

        return $this->render('larp/characters.html.twig', [
            'settings' => $this->settings->get(),
            'larp' => $larp,
            'edition' => $edition,
        ]);
    }

    #[Route('/larp/{slug}/postac/{characterSlug}', name: 'app_character_show')]
    public function character(string $slug, string $characterSlug): Response
    {
        $larp = $this->findLarp($slug);

        // Newest edition first, so a reused character name points at the current run.
        $character = null;
        foreach (array_reverse($larp->getSortedEditions()) as $edition) {
            $character = $this->characters->findVisibleBySlug($edition, $characterSlug);
            if ($character instanceof Character) {
                break;
            }
        }
        if (null === $character) {
            throw new NotFoundHttpException();
        }

        return $this->render('character/show.html.twig', [
            'settings' => $this->settings->get(),
            'larp' => $larp,
            'edition' => $character->getEdition(),
            'character' => $character,
        ]);
    }

    private function findLarp(string $slug): Larp
    {
        return $this->larps->findPublishedBySlug($slug) ?? throw new NotFoundHttpException();
    }
}
