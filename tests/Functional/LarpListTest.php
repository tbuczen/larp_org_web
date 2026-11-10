<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Larp;
use App\Entity\LarpEdition;
use App\Enum\RecruitmentStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/** "Nasze larpy": every edition as its own event, past ones hidden by default. */
final class LarpListTest extends WebTestCase
{
    use RollbackTransactionTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->startTransaction($this->client);
    }

    public function testUpcomingEditionsAreListedAndPastOnesHidden(): void
    {
        $this->addEdition('cybercrush', '2. edycja', '+40 days', RecruitmentStatus::Open);
        $this->addEdition('pradawne-zwoje', '4. edycja', '+300 days', RecruitmentStatus::NotStarted);

        $crawler = $this->client->request('GET', '/larpy');
        self::assertResponseIsSuccessful();

        $upcoming = $crawler->filter('#upcoming .event-card');
        self::assertSame(['Cybercrush 2. edycja', 'Pradawne Zwoje 4. edycja'], $upcoming->each(
            static fn (Crawler $c): string => trim($c->filter('.larp-card__title')->text()).' '.trim($c->filter('.event-card__edition')->text()),
        ), 'soonest first');
        self::assertSame('/larp/cybercrush/2-edycja', $upcoming->first()->filter('.larp-card__title a')->attr('href'));
        self::assertCount(1, $upcoming->first()->filter('.badge--open'));
        self::assertStringContainsString('--color-bg', (string) $upcoming->first()->attr('style'), "in the LARP's colours");

        self::assertSelectorNotExists('#past');
        self::assertSelectorNotExists('.event-card--past');
        self::assertSelectorTextContains('.event-more a', 'Pokaż 8 minionych edycji');
        self::assertSelectorTextContains('#initiatives', 'Wyprawa Rycerska Talabeclandu');
        self::assertSelectorTextNotContains('body', 'Projekt w przygotowaniu');
        self::assertSelectorTextContains('.event-filter a[aria-current="page"]', 'Nadchodzące');
    }

    public function testPastEditionsOnRequestNewestFirst(): void
    {
        $crawler = $this->client->request('GET', '/larpy?minione=1');
        self::assertResponseIsSuccessful();

        $past = $crawler->filter('#past .event-card');
        self::assertCount(8, $past, 'every past edition of every LARP');
        self::assertSame(
            ['Pradawne Zwoje 3. edycja', 'Cybercrush 1. edycja', 'Pradawne Zwoje 2. edycja'],
            \array_slice($past->each(static fn (Crawler $c): string => trim($c->filter('.larp-card__title')->text()).' '.trim($c->filter('.event-card__edition')->text())), 0, 3),
        );
        self::assertCount(0, $past->filter('.badge'), 'no recruitment badges on past events');
        self::assertSame('/larp/vault-47/3-edycja', $past->reduce(static fn (Crawler $c): bool => str_contains($c->text(), 'Vault 47'))->first()->filter('.larp-card__title a')->attr('href'));
        self::assertSelectorTextContains('.event-filter a[aria-current="page"]', 'Wszystkie');
    }

    public function testNothingUpcomingSaysSoAndOffersThePast(): void
    {
        // All sample editions are in the past.
        $crawler = $this->client->request('GET', '/larpy');
        self::assertSelectorTextContains('#upcoming .event-empty', 'Nie ogłosiliśmy jeszcze kolejnych terminów');
        self::assertSame('/larpy?minione=1', $crawler->filter('#upcoming .event-empty a')->attr('href'));
    }

    private function addEdition(string $larp, string $name, string $start, RecruitmentStatus $recruitment): void
    {
        $entity = $this->em()->getRepository(Larp::class)->findOneBy(['slug' => $larp]);
        self::assertInstanceOf(Larp::class, $entity);
        $date = new \DateTimeImmutable($start);
        $entity->addEdition((new LarpEdition())->setName($name)->setStartDate($date)->setEndDate($date->modify('+2 days'))->setRecruitment($recruitment));
        $this->em()->flush();
    }

    private function em(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
