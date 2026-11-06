<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PublicLarpPagesTest extends WebTestCase
{
    public function testLarpListHasSectionsAndHidesDrafts(): void
    {
        $client = self::createClient();
        $client->request('GET', '/larpy');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#current', 'Pradawne Zwoje');
        self::assertSelectorTextContains('#current', 'Cybercrush');
        self::assertSelectorTextContains('#past', 'Vault 47');
        self::assertSelectorTextContains('#past', '3 edycje · 2023–2025');
        self::assertSelectorTextContains('#initiatives', 'Wyprawa Rycerska Talabeclandu');
        self::assertSelectorTextNotContains('body', 'Projekt w przygotowaniu');
    }

    public function testLarpPageShowsCurrentEditionAndRecruitment(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/larp/pradawne-zwoje');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Pradawne Zwoje');
        self::assertSelectorTextContains('.facts', '6–9.VIII.2026');
        self::assertSelectorTextContains('.facts', 'Czyżowice');
        self::assertSame('https://fb.me/e/69mffUNUK', $crawler->filter('a.badge--open')->attr('href'));
        self::assertSelectorTextContains('.larp-characters', 'Kapłanka Mary');
    }

    public function testCharacterListShowsTakenButNotHiddenCharacters(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/larp/pradawne-zwoje/postacie');

        self::assertResponseIsSuccessful();
        self::assertCount(6, $crawler->filter('.match-card'));
        self::assertSelectorTextContains('.match-card.is-taken', 'Uczeń maga Eirik');
        self::assertSelectorTextNotContains('body', 'Tajna postać');
    }

    public function testCharacterPageShowsTagsWithPercentages(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/larp/pradawne-zwoje/postac/kaplanka-mary');

        self::assertResponseIsSuccessful();
        $gameplay = $crawler->filter('.tag-bar--gameplay');
        self::assertCount(3, $gameplay);
        self::assertStringContainsString('Duchowość / religia', $gameplay->first()->text());
        self::assertStringContainsString('50%', $gameplay->first()->text());
        self::assertSelectorTextContains('.tag-bar--trigger', 'Religia');
    }

    /** @return iterable<string, array{string}> */
    public static function notFoundUrls(): iterable
    {
        yield 'draft LARP' => ['/larp/projekt-w-przygotowaniu'];
        yield 'hidden character' => ['/larp/pradawne-zwoje/postac/tajna-postac'];
        yield 'characters of an initiative' => ['/larp/wyprawa-rycerska-talabeclandu/postacie'];
        yield 'unknown LARP' => ['/larp/nie-ma-takiego'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('notFoundUrls')]
    public function testNotFound(string $url): void
    {
        $client = self::createClient();
        $client->request('GET', $url);

        self::assertResponseStatusCodeSame(404);
    }
}
