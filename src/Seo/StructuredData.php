<?php

declare(strict_types=1);

namespace App\Seo;

use App\Entity\Larp;
use App\Entity\LarpEdition;
use App\Entity\SiteSettings;
use App\Enum\RecruitmentStatus;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * schema.org data (JSON-LD) that search engines read to show rich results: the
 * organisation, each LARP edition as an Event (date, place, price, sign-up link)
 * and breadcrumbs. Templates print it with {{ json_ld(...) }}.
 */
final class StructuredData
{
    public function __construct(
        private readonly UrlGeneratorInterface $urls,
        private readonly SeoUrls $seoUrls,
    ) {
    }

    /** @return array<string, mixed> */
    public function organization(SiteSettings $settings): array
    {
        $home = $this->urls->generate('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL);

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            '@id' => $home.'#organization',
            'name' => $settings->getOrganizationName(),
            'url' => $home,
            'description' => Text::meta($settings->getTagline()),
            'logo' => $this->seoUrls->defaultImage(),
            'email' => $settings->getContactEmail(),
            'sameAs' => array_values(array_map(static fn ($link): string => $link->url, $settings->getSocialButtons())) ?: null,
        ], static fn (mixed $v): bool => null !== $v && '' !== $v);
    }

    /** @return array<string, mixed> */
    public function website(SiteSettings $settings): array
    {
        $home = $this->urls->generate('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL);

        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $settings->getOrganizationName(),
            'url' => $home,
            'inLanguage' => 'pl-PL',
            'publisher' => ['@id' => $home.'#organization'],
        ];
    }

    /**
     * One edition as an Event. Without a start date there's nothing a search
     * engine can use, so null.
     *
     * @return array<string, mixed>|null
     */
    public function event(LarpEdition $edition, SiteSettings $settings): ?array
    {
        $larp = $edition->getLarp();
        $start = $edition->getStartDate();
        if (null === $larp || null === $start) {
            return null;
        }

        $url = $this->urls->generate('app_edition_show', ['slug' => $larp->getSlug(), 'edition' => $edition->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL);
        $home = $this->urls->generate('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $location = $edition->getLocation();

        $event = [
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => $edition->getTitle(),
            'url' => $url,
            'startDate' => $start->format('Y-m-d'),
            'endDate' => $edition->getEndOrStartDate()?->format('Y-m-d'),
            'eventStatus' => 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'description' => Text::meta($edition->getDisplayTeaser() ?? $edition->getDescription() ?? $larp->getDescription()),
            'image' => ($image = $this->seoUrls->imageFor($larp, $edition)) ? [$image] : null,
            'location' => null === $location ? null : array_filter([
                '@type' => 'Place',
                'name' => $location,
                'address' => $location,
                'hasMap' => $edition->getLocationUrl(),
            ]),
            'organizer' => [
                '@type' => 'Organization',
                'name' => $settings->getOrganizationName(),
                'url' => $home,
            ],
            'offers' => $this->offer($edition, $url),
        ];

        return array_filter($event, static fn (mixed $v): bool => null !== $v && '' !== $v);
    }

    /**
     * Breadcrumbs from [name, absolute url] pairs, the home page first.
     *
     * @param list<array{0: string, 1: string}> $items
     *
     * @return array<string, mixed>
     */
    public function breadcrumbs(array $items): array
    {
        $list = [];
        foreach ($items as $i => [$name, $url]) {
            $list[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $name, 'item' => $url];
        }

        return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $list];
    }

    /**
     * Breadcrumbs for a LARP page and the pages under it.
     *
     * @param list<array{0: string, 1: string}> $more further [name, absolute url] steps
     *
     * @return array<string, mixed>
     */
    public function larpBreadcrumbs(SiteSettings $settings, Larp $larp, ?LarpEdition $edition = null, array $more = []): array
    {
        $items = [
            [$settings->getOrganizationName(), $this->urls->generate('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL)],
            [$larp->getName(), $this->urls->generate('app_larp_show', ['slug' => $larp->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL)],
        ];
        if (null !== $edition) {
            $items[] = [$edition->getName(), $this->urls->generate('app_edition_show', ['slug' => $larp->getSlug(), 'edition' => $edition->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL)];
        }

        return $this->breadcrumbs([...$items, ...$more]);
    }

    /** @return array<string, mixed>|null */
    private function offer(LarpEdition $edition, string $url): ?array
    {
        $price = Text::price($edition->getDisplayPrice());
        $registration = $edition->getRegistrationUrl();
        if (null === $price && null === $registration) {
            return null;
        }

        return array_filter([
            '@type' => 'Offer',
            'url' => $registration ?? $url,
            'price' => $price['amount'] ?? null,
            'priceCurrency' => $price['currency'] ?? null,
            'availability' => match ($edition->getRecruitment()) {
                RecruitmentStatus::Open => 'https://schema.org/InStock',
                RecruitmentStatus::Closed => 'https://schema.org/SoldOut',
                RecruitmentStatus::NotStarted => 'https://schema.org/PreOrder',
            },
        ], static fn (mixed $v): bool => null !== $v);
    }
}
