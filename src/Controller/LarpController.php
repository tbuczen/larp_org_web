<?php

declare(strict_types=1);

namespace App\Controller;

use App\Calendar\IcsCalendar;
use App\Entity\Character;
use App\Entity\Larp;
use App\Entity\LarpEdition;
use App\Repository\CharacterRepository;
use App\Repository\GalleryRepository;
use App\Repository\LarpRepository;
use App\Repository\SiteSettingsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Public LARP pages:
 *   /larp/{slug}                         the LARP, showing its current edition
 *   /larp/{slug}/{edition}               one edition: its dates, characters, galleries
 *   /larp/{slug}/{edition}/postacie      character matcher for that edition
 *   /larp/{slug}/{edition}/postac/{x}    a character of that edition
 * Short forms without the edition (/larp/{slug}/postacie, …) mean the current edition.
 */
class LarpController extends AbstractController
{
    /** An edition address: a slug that isn't one of the other pages under /larp/{slug}/. */
    private const EDITION = '(?!(?:postacie|postac|galerie)$)[a-z0-9]+(?:-[a-z0-9]+)*';

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
            'characterCounts' => $this->characters->countVisibleByEdition($larp),
        ]);
    }

    /** The current edition as an .ics file ("add to calendar"). */
    #[Route('/larp/{slug}/kalendarz.ics', name: 'app_larp_calendar')]
    public function calendar(string $slug): Response
    {
        $larp = $this->findLarp($slug);

        return $this->ics($larp, $larp->isInitiative() ? null : $larp->getCurrentEdition());
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

        return $this->matcher($larp, $larp->isInitiative() ? null : $larp->getCurrentEdition());
    }

    /** Old character address without the edition: forwards to the newest edition that has it. */
    #[Route('/larp/{slug}/postac/{characterSlug}', name: 'app_character_show')]
    public function character(string $slug, string $characterSlug): RedirectResponse
    {
        $larp = $this->findLarp($slug);
        foreach (array_reverse($larp->getSortedEditions()) as $edition) {
            $character = $this->characters->findVisibleBySlug($edition, $characterSlug);
            if ($character instanceof Character) {
                return $this->redirectToRoute('app_edition_character', [
                    'slug' => $larp->getSlug(),
                    'edition' => $edition->getSlug(),
                    'characterSlug' => $character->getSlug(),
                ], Response::HTTP_MOVED_PERMANENTLY);
            }
        }

        throw new NotFoundHttpException();
    }

    // ------------------------------------------------------------- editions

    #[Route('/larp/{slug}/{edition}', name: 'app_edition_show', requirements: ['edition' => self::EDITION], priority: -10)]
    public function edition(string $slug, string $edition): Response
    {
        $larp = $this->findLarp($slug);
        $run = $this->findEdition($larp, $edition);

        return $this->render('larp/edition.html.twig', [
            'settings' => $this->settings->get(),
            'larp' => $larp,
            'edition' => $run,
            'isCurrent' => $run === $larp->getCurrentEdition(),
            'characters' => $this->characters->findVisibleForEdition($run),
            'galleries' => $this->galleries->findVisibleForEdition($run),
        ]);
    }

    #[Route('/larp/{slug}/{edition}/postacie', name: 'app_edition_characters', requirements: ['edition' => self::EDITION], priority: -10)]
    public function editionCharacters(string $slug, string $edition): Response
    {
        $larp = $this->findLarp($slug);

        return $this->matcher($larp, $this->findEdition($larp, $edition));
    }

    #[Route('/larp/{slug}/{edition}/postac/{characterSlug}', name: 'app_edition_character', requirements: ['edition' => self::EDITION], priority: -10)]
    public function editionCharacter(string $slug, string $edition, string $characterSlug): Response
    {
        $larp = $this->findLarp($slug);
        $run = $this->findEdition($larp, $edition);
        $character = $this->characters->findVisibleBySlug($run, $characterSlug) ?? throw new NotFoundHttpException();

        return $this->render('character/show.html.twig', [
            'settings' => $this->settings->get(),
            'larp' => $larp,
            'edition' => $run,
            'character' => $character,
        ]);
    }

    #[Route('/larp/{slug}/{edition}/kalendarz.ics', name: 'app_edition_calendar', requirements: ['edition' => self::EDITION], priority: -10)]
    public function editionCalendar(string $slug, string $edition): Response
    {
        $larp = $this->findLarp($slug);

        return $this->ics($larp, $this->findEdition($larp, $edition));
    }

    private function matcher(Larp $larp, ?LarpEdition $edition): Response
    {
        if (null === $edition) {
            throw new NotFoundHttpException();
        }

        return $this->render('larp/characters.html.twig', [
            'settings' => $this->settings->get(),
            'larp' => $larp,
            'edition' => $edition,
            'isCurrent' => $edition === $larp->getCurrentEdition(),
        ]);
    }

    private function ics(Larp $larp, ?LarpEdition $edition): Response
    {
        $ics = null === $edition ? null : IcsCalendar::forEdition(
            $edition,
            $this->generateUrl('app_edition_show', ['slug' => $larp->getSlug(), 'edition' => $edition->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
            $this->settings->get()->getOrganizationName(),
        );
        if (null === $ics) {
            throw new NotFoundHttpException();
        }

        return new Response($ics, Response::HTTP_OK, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $larp->getSlug().'-'.$edition?->getSlug().'.ics'),
        ]);
    }

    private function findLarp(string $slug): Larp
    {
        return $this->larps->findPublishedBySlug($slug) ?? throw new NotFoundHttpException();
    }

    private function findEdition(Larp $larp, string $slug): LarpEdition
    {
        if ($larp->isInitiative()) {
            throw new NotFoundHttpException();
        }

        return $larp->getEditionBySlug($slug) ?? throw new NotFoundHttpException();
    }
}
