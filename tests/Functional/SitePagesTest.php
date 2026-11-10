<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class SitePagesTest extends WebTestCase
{
    public function testFrontPageIsBuiltFromBlocksInTheLasTajemnicTheme(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('html[data-theme="lastajemnic"]');
        self::assertSelectorTextContains('.hero__title', 'Przeżyj prawdziwą przygodę');
        self::assertSelectorExists('.hero__image[src*="themes/lastajemnic/assets/images/hero"]');
        self::assertCount(3, $crawler->filter('.block--larp-showcase'));
        self::assertSelectorExists('.block--matcher-teaser a[href="/postacie"]');
        self::assertSelectorExists('.block--social-links .social-links');

        // Base CSS first, then the theme's.
        $styles = $crawler->filter('link[rel="stylesheet"]')->each(static fn (Crawler $l): string => (string) $l->attr('href'));
        $base = array_search(true, array_map(static fn ($h) => str_contains($h, 'themes/base/assets/styles/tokens'), $styles), true);
        $theme = array_search(true, array_map(static fn ($h) => str_contains($h, 'themes/lastajemnic/assets/styles/tokens'), $styles), true);
        self::assertIsInt($base);
        self::assertIsInt($theme);
        self::assertLessThan($theme, $base);
        self::assertSelectorExists('script[src*="themes/lastajemnic/assets/scripts/effects"]');
        self::assertSelectorExists('link[rel="preload"][as="font"][crossorigin][href*="saira-extra-condensed-latin-600"]');
        self::assertSelectorExists('.block--hero[data-controller="scroll-fx"]');
    }

    public function testContentPagesBySlug(): void
    {
        $client = self::createClient();

        $client->request('GET', '/stowarzyszenie');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Stowarzyszenie');
        self::assertSelectorTextContains('.block--rich-text', 'Kim jesteśmy');

        $client->request('GET', '/kontakt');
        self::assertSelectorTextContains('.block--social-links', 'stowarzyszenie@lastajemnic.org');

        $client->request('GET', '/nie-ma-takiej-strony');
        self::assertResponseStatusCodeSame(404);

        $client->request('GET', '/strona-glowna');
        self::assertResponseStatusCodeSame(404, 'the front page is only served at /');
    }

    public function testMainMenuWithDropdownsAndActiveItem(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/larp/vault-47');

        $top = $crawler->filter('.site-nav .menu > .menu__item > .menu__link, .site-nav .menu > .menu__item summary')
            ->each(static fn (Crawler $a): string => trim($a->text()));
        self::assertSame(['Stowarzyszenie', 'Nasze larpy', 'Inne inicjatywy', 'Postacie', 'Galerie', 'Kontakt'], $top);

        $larps = $crawler->filter('.site-nav details')->first();
        self::assertSame(['Zobacz wszystko', 'Cybercrush', 'Pradawne Zwoje', 'Vault 47', 'Czasy Pogardy'], $larps->filter('.submenu__list a')->each(static fn (Crawler $a): string => $a->text()));
        self::assertSelectorExists('.site-nav summary.is-active');
        self::assertSame('Vault 47', $crawler->filter('.site-nav a[aria-current="page"]')->text());

        self::assertSame(['Stowarzyszenie', 'Larpy', 'Kontakt'], $crawler->filter('.menu--footer a')->each(static fn (Crawler $a): string => $a->text()));
    }

    public function testHiddenTargetsDisappearFromTheMenu(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $connection->beginTransaction();
        try {
            $connection->executeStatement("UPDATE larp SET status = 'draft' WHERE slug = 'czasy-pogardy'");
            $crawler = $client->request('GET', '/');
            self::assertStringNotContainsString('Czasy Pogardy', $crawler->filter('.site-nav')->text());
        } finally {
            $connection->rollBack();
        }
    }
}
