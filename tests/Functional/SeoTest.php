<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class SeoTest extends WebTestCase
{
    use RollbackTransactionTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->startTransaction($this->client);
    }

    public function testHomePageDescribesTheOrganisation(): void
    {
        $crawler = $this->client->request('GET', '/');

        self::assertSame('http://localhost/', $crawler->filter('link[rel=canonical]')->attr('href'));
        self::assertNotEmpty($crawler->filter('meta[name=description]')->attr('content'));
        self::assertSame('website', $crawler->filter('meta[property="og:type"]')->attr('content'));
        self::assertNotEmpty($crawler->filter('meta[property="og:title"]')->attr('content'));
        foreach ($crawler->filter('meta[property="og:image"]')->each(static fn (Crawler $m): string => (string) $m->attr('content')) as $image) {
            self::assertStringStartsWith('http://localhost/', $image, 'share images need absolute URLs');
        }

        $types = array_column($this->jsonLd($crawler), '@type');
        self::assertContains('Organization', $types);
        self::assertContains('WebSite', $types);
    }

    public function testEditionPageIsAnEventWithBreadcrumbs(): void
    {
        $crawler = $this->client->request('GET', '/larp/pradawne-zwoje/3-edycja');

        $items = $this->jsonLd($crawler);
        $event = $this->first($items, 'Event');
        self::assertSame('http://localhost/larp/pradawne-zwoje/3-edycja', $event['url']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $event['startDate']);
        self::assertSame('https://schema.org/OfflineEventAttendanceMode', $event['eventAttendanceMode']);
        self::assertArrayHasKey('organizer', $event);

        $crumbs = $this->first($items, 'BreadcrumbList')['itemListElement'];
        self::assertSame(['Pradawne Zwoje', '3. edycja'], [$crumbs[1]['name'], $crumbs[2]['name']]);
    }

    public function testLarpPageListsUpcomingEditionsAsEvents(): void
    {
        $crawler = $this->client->request('GET', '/larp/pradawne-zwoje');

        self::assertSame('http://localhost/larp/pradawne-zwoje', $crawler->filter('link[rel=canonical]')->attr('href'));
        self::assertContains('BreadcrumbList', array_column($this->jsonLd($crawler), '@type'));
    }

    public function testCanonicalDropsFilterQueries(): void
    {
        $crawler = $this->client->request('GET', '/postacie?ocena[1]=2&triggery[]=3');

        self::assertSame('http://localhost/postacie', $crawler->filter('link[rel=canonical]')->attr('href'));
        self::assertCount(0, $crawler->filter('meta[name=robots]'));
    }

    public function testCharacterPageHasItsOwnDescription(): void
    {
        $crawler = $this->client->request('GET', '/larp/pradawne-zwoje');
        $href = $crawler->filter('.larp-characters a[href*="/postac/"]')->first()->attr('href');
        self::assertNotNull($href);

        $crawler = $this->client->request('GET', $href);
        self::assertResponseIsSuccessful();
        self::assertSame('article', $crawler->filter('meta[property="og:type"]')->attr('content'));
        self::assertCount(5, $this->first($this->jsonLd($crawler), 'BreadcrumbList')['itemListElement'] ?? [], 'home, LARP, edition, characters, the character');
    }

    public function testSitemapListsPublicPagesOnly(): void
    {
        $this->client->request('GET', '/sitemap.xml');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/xml; charset=UTF-8');

        $xml = simplexml_load_string((string) $this->client->getResponse()->getContent());
        self::assertNotFalse($xml);
        $locs = [];
        foreach ($xml->url as $url) {
            $locs[] = (string) $url->loc;
        }

        self::assertContains('http://localhost/', $locs);
        self::assertContains('http://localhost/larpy', $locs);
        self::assertContains('http://localhost/larp/pradawne-zwoje', $locs);
        self::assertContains('http://localhost/larp/pradawne-zwoje/3-edycja', $locs);
        self::assertContains('http://localhost/larp/pradawne-zwoje/3-edycja/postacie', $locs);
        self::assertNotEmpty(array_filter($locs, static fn (string $l): bool => str_contains($l, '/postac/')), 'character pages');
        foreach ($locs as $loc) {
            self::assertStringNotContainsString('projekt-w-przygotowaniu', $loc, 'no drafts');
            self::assertStringNotContainsString('tajna-postac', $loc, 'no hidden characters');
            self::assertStringNotContainsString('/admin', $loc);
        }
        self::assertSame(\count($locs), \count(array_unique($locs)), 'no duplicates');
    }

    public function testRobotsTxtAndNoIndexForTheAdmin(): void
    {
        $this->client->request('GET', '/robots.txt');
        self::assertResponseIsSuccessful();
        $robots = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString("Disallow: /admin\n", $robots);
        self::assertStringContainsString('Sitemap: http://localhost/sitemap.xml', $robots);

        $this->client->request('GET', '/login');
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow');
        // Symfony itself sends "noindex" on every page outside production; public pages
        // must not get the admin's header on top of that.
        $this->client->request('GET', '/larpy');
        self::assertNotSame('noindex, nofollow', $this->client->getResponse()->headers->get('X-Robots-Tag'));
    }

    /** @return list<array<string, mixed>> */
    private function jsonLd(Crawler $crawler): array
    {
        return $crawler->filter('script[type="application/ld+json"]')->each(
            static fn (Crawler $s): array => (array) json_decode($s->text(), true, 512, \JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @param list<array<string, mixed>> $items
     *
     * @return array<string, mixed>
     */
    private function first(array $items, string $type): array
    {
        foreach ($items as $item) {
            if (($item['@type'] ?? null) === $type) {
                return $item;
            }
        }
        self::fail('no '.$type);
    }
}
