<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Tag;
use App\Twig\Components\CharacterMatcher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;

final class CharacterMatcherTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    public function testWithoutPreferencesEveryoneIsFiftyPercent(): void
    {
        $crawler = $this->client->request('GET', '/larp/pradawne-zwoje/postacie');

        self::assertResponseIsSuccessful();
        self::assertSame(array_fill(0, 6, '50%'), $this->scores($crawler));
        self::assertSelectorTextNotContains('.matcher__results', 'Tajna postać');
    }

    public function testRatingsFromTheUrlRankCharacters(): void
    {
        // Love combat, hate politics.
        $crawler = $this->get('/larp/pradawne-zwoje/postacie', [
            'ocena' => [$this->tagId('Konflikt / walka') => 2, $this->tagId('Polityka / intryga') => -2],
        ]);

        // Hrafna: 60% combat (+2), 30% + 10% unrated → (2×60)/100 = 1.2 → 80%
        // Jarl: 50% politics (−2), 30% unrated, 20% combat (+2) → −0.6 → 35%
        self::assertSame(['Najemniczka Hrafna', 'Kapłanka Mary'], \array_slice($this->names($crawler), 0, 2));
        self::assertSame('80%', $this->scores($crawler)[0]);
        self::assertSame('Jarl Ulfgar', $this->names($crawler)[5]);
        self::assertSame('35%', $this->scores($crawler)[5]);
        self::assertSelectorTextContains('.match-card:last-child .match-card__dislikes', 'Polityka / intryga');

        // The chosen radio buttons are checked again.
        self::assertCount(1, $crawler->filter('input[name="ratings['.$this->tagId('Konflikt / walka').']"][value="2"][checked]'));
    }

    public function testTriggersAreHighlightedOrHidden(): void
    {
        $death = $this->tagId('Śmierć');

        $crawler = $this->get('/larp/pradawne-zwoje/postacie', ['triggery' => [$death]]);
        self::assertCount(6, $crawler->filter('.match-card'));
        self::assertSame(['Jarl Ulfgar', 'Najemniczka Hrafna'], $crawler->filter('.match-card.has-triggers h3 a')->each(static fn (Crawler $a) => $a->text()));
        self::assertSelectorTextContains('.match-card.has-triggers .match-card__warning', 'Śmierć');

        $crawler = $this->get('/larp/pradawne-zwoje/postacie', ['triggery' => [$death], 'ukryj' => 1]);
        self::assertCount(4, $crawler->filter('.match-card'));
        self::assertNotContains('Jarl Ulfgar', $this->names($crawler));
    }

    public function testAllLarpsPageWithFilters(): void
    {
        $crawler = $this->client->request('GET', '/postacie');
        self::assertResponseIsSuccessful();
        self::assertCount(9, $crawler->filter('.match-card'), '6 Pradawne Zwoje + 3 Cybercrush');
        self::assertCount(3, $crawler->filter('select[data-model="larpFilter"] option'), 'all + 2 LARPs');

        $crawler = $this->get('/postacie', ['larp' => 'cybercrush']);
        self::assertSame(['Decker „Ghost”', 'Fixer Mama Lou', 'Samuraj ulicy Kenji'], $this->names($crawler));

        // "Any" casting fits every filter.
        $crawler = $this->get('/postacie', ['larp' => 'cybercrush', 'obsada' => 'female']);
        self::assertSame(['Decker „Ghost”', 'Fixer Mama Lou'], $this->names($crawler));

        $crawler = $this->get('/larp/pradawne-zwoje/postacie', ['wolne' => 1]);
        self::assertNotContains('Uczeń maga Eirik', $this->names($crawler), 'taken characters are filtered out');
    }

    public function testInvalidUrlValuesAreIgnored(): void
    {
        $crawler = $this->get('/larp/pradawne-zwoje/postacie', [
            'ocena' => ['abc' => 2, $this->tagId('Konflikt / walka') => 99],
            'triggery' => ['x'],
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame('Najemniczka Hrafna', $this->names($crawler)[0], '99 is treated as +2');
        self::assertCount(0, $crawler->filter('.match-card.has-triggers'));
    }

    public function testRateAndResetActions(): void
    {
        $component = $this->createLiveComponent(CharacterMatcher::class, ['larpSlug' => 'pradawne-zwoje'], $this->client);
        $combat = $this->tagId('Konflikt / walka');

        $component->call('rate', ['tag' => $combat, 'value' => 2]);
        $crawler = $component->render()->crawler();
        self::assertSame('Najemniczka Hrafna', $crawler->filter('.match-card h3 a')->first()->text());
        self::assertSame([$combat => 2], $this->ratings($component));

        // Neutral removes the rating, so it doesn't end up in the URL.
        $component->call('rate', ['tag' => $combat, 'value' => 0]);
        self::assertSame([], $this->ratings($component));

        $component->call('rate', ['tag' => $combat, 'value' => -2]);
        $component->set('onlyAvailable', true);
        $component->call('reset');
        self::assertSame([], $this->ratings($component));
        self::assertNull($component->component()->onlyAvailable);
    }

    public function testUncheckedFiltersBecomeNull(): void
    {
        $component = $this->createLiveComponent(CharacterMatcher::class, [], $this->client);

        $component->set('hideTriggers', true);
        self::assertTrue($component->component()->hideTriggers);
        $component->set('hideTriggers', false);
        self::assertNull($component->component()->hideTriggers);
        $component->set('casting', '');
        self::assertNull($component->component()->casting);
    }

    /**
     * Reads the component's ratings afresh (a helper, so static analysis doesn't
     * assume the value can't change between actions).
     *
     * @return array<int|string, int|string>
     */
    private function ratings(TestLiveComponent $component): array
    {
        $matcher = $component->component();
        self::assertInstanceOf(CharacterMatcher::class, $matcher);

        return $matcher->ratings;
    }

    /** @param array<string, mixed> $query */
    private function get(string $path, array $query): Crawler
    {
        $crawler = $this->client->request('GET', $path.'?'.http_build_query($query));
        self::assertResponseIsSuccessful();

        return $crawler;
    }

    /** @return list<string> */
    private function names(Crawler $crawler): array
    {
        return $crawler->filter('.match-card h3 a')->each(static fn (Crawler $a): string => $a->text());
    }

    /** @return list<string> */
    private function scores(Crawler $crawler): array
    {
        return $crawler->filter('.match-card__score strong')->each(static fn (Crawler $s): string => $s->text());
    }

    private function tagId(string $name): int
    {
        $tag = self::getContainer()->get(EntityManagerInterface::class)->getRepository(Tag::class)->findOneBy(['name' => $name]);
        self::assertNotNull($tag, $name);

        return (int) $tag->getId();
    }
}
