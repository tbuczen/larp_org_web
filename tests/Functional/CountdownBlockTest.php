<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Block\Type\CountdownBlock;
use App\Entity\Larp;
use App\Entity\LarpEdition;
use App\Entity\Page;
use App\Entity\PageBlock;
use App\Enum\RecruitmentStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CountdownBlockTest extends WebTestCase
{
    use RollbackTransactionTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->startTransaction($this->client);
    }

    public function testNextLarpWidgetWithCalendarOnACustomPage(): void
    {
        $this->addEdition('vault-47', '4. edycja', '+90 days');
        $this->addEdition('cybercrush', '2. edycja', '+40 days');
        $this->page(['larp' => CountdownBlock::NEXT, 'show_calendar' => true]);

        $crawler = $this->client->request('GET', '/odliczanie');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.next-larp .eyebrow', 'Najbliższy larp');
        self::assertSelectorTextContains('.next-larp__title', 'Cybercrush', 'the soonest upcoming edition of any LARP');
        self::assertSelectorTextContains('.next-larp__edition', '2. edycja');
        self::assertSelectorExists('.next-larp [data-controller="countdown"]');
        self::assertSame('/larp/cybercrush/2-edycja', $crawler->filter('.next-larp .button--primary')->attr('href'));
        self::assertSelectorExists('.next-larp a[href="/larp/cybercrush/2-edycja/kalendarz.ics"]');
        self::assertStringContainsString('--color-bg', (string) $crawler->filter('.next-larp')->attr('style'), "in the LARP's colours");

        $start = new \DateTimeImmutable('+40 days');
        $eventDays = $crawler->filter('.mini-calendar td.is-event')->each(static fn ($td): string => trim($td->text()));
        self::assertContains((string) (int) $start->format('j'), $eventDays);
        self::assertGreaterThanOrEqual(1, \count($eventDays));
    }

    public function testAChosenLarpAndTheTextWhenNothingIsPlanned(): void
    {
        $vault = $this->larp('vault-47');
        $this->page(['larp' => (string) $vault->getId(), 'show_calendar' => false, 'empty_text' => 'Nowe terminy ogłosimy wkrótce']);

        // Vault 47's editions are all in the past.
        $this->client->request('GET', '/odliczanie');
        self::assertSelectorNotExists('.next-larp');
        self::assertSelectorTextContains('.next-larp__empty', 'Nowe terminy ogłosimy wkrótce');

        $this->addEdition('vault-47', '4. edycja', '+90 days');
        $this->client->request('GET', '/odliczanie');
        self::assertSelectorTextContains('.next-larp__title', 'Vault 47');
        self::assertSelectorNotExists('.mini-calendar', 'calendar switched off');
    }

    public function testWithoutAnythingUpcomingAndNoTextTheBlockHides(): void
    {
        $this->page(['larp' => CountdownBlock::NEXT]);
        $this->client->request('GET', '/odliczanie');
        self::assertSelectorNotExists('.block--countdown');
    }

    /** @param array<string, mixed> $settings */
    private function page(array $settings): void
    {
        $page = (new Page())->setTitle('Odliczanie')->setSlug('odliczanie')->setPublished(true);
        $page->addBlock(new PageBlock('countdown', $settings));
        $this->em()->persist($page);
        $this->em()->flush();
    }

    private function addEdition(string $larp, string $name, string $start): void
    {
        $date = new \DateTimeImmutable($start);
        $this->larp($larp)->addEdition((new LarpEdition())->setName($name)->setStartDate($date)->setEndDate($date->modify('+2 days'))->setRecruitment(RecruitmentStatus::Open));
        $this->em()->flush();
    }

    private function larp(string $slug): Larp
    {
        $larp = $this->em()->getRepository(Larp::class)->findOneBy(['slug' => $slug]);
        self::assertInstanceOf(Larp::class, $larp);

        return $larp;
    }

    private function em(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
