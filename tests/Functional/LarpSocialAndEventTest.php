<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\DataFixtures\AppFixtures;
use App\Entity\Larp;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class LarpSocialAndEventTest extends WebTestCase
{
    use RollbackTransactionTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->startTransaction($this->client);
    }

    public function testLarpPageShowsItsFacebookEventAndOwnSocialLinks(): void
    {
        $crawler = $this->client->request('GET', '/larp/pradawne-zwoje');
        self::assertResponseIsSuccessful();

        $event = $crawler->filter('.larp-actions a[href="https://fb.me/e/hlDppZ9HB"]');
        self::assertCount(1, $event);
        self::assertSame('_blank', $event->attr('target'));
        self::assertStringContainsString('Wydarzenie na Facebooku', $event->text());

        self::assertSelectorTextContains('.larp-social__label', 'Śledź larpa');
        self::assertSame(
            ['https://www.facebook.com/pradawnezwoje', 'https://www.facebook.com/groups/287396224278365/'],
            $crawler->filter('.larp-social a')->each(static fn (Crawler $a): string => (string) $a->attr('href')),
        );
        self::assertSame(['Facebook', 'Grupa graczy'], $crawler->filter('.larp-social a span')->each(static fn (Crawler $s): string => $s->text()));
    }

    public function testWithoutOwnLinksTheOrganizationsAreShownAndNoEventButton(): void
    {
        $crawler = $this->client->request('GET', '/larp/czasy-pogardy');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.larp-actions');
        self::assertSelectorTextContains('.larp-social__label', 'Śledź nas');
        self::assertSame('https://www.facebook.com/StowarzyszenieLasTajemnic', $crawler->filter('.larp-social a')->first()->attr('href'));
    }

    public function testCardsShowAFacebookEventIcon(): void
    {
        $crawler = $this->client->request('GET', '/larpy');
        $card = $crawler->filter('.larp-card')->reduce(static fn (Crawler $c): bool => str_contains($c->text(), 'Pradawne Zwoje'));
        self::assertCount(1, $card->filter('.larp-card__event[href="https://fb.me/e/hlDppZ9HB"][target="_blank"]'));
        $cyber = $crawler->filter('.larp-card')->reduce(static fn (Crawler $c): bool => str_contains($c->text(), 'Cybercrush'));
        self::assertCount(0, $cyber->filter('.larp-card__event'));
    }

    public function testAddToCalendarForUpcomingEditions(): void
    {
        // Move Pradawne Zwoje's current edition into the future.
        $larp = $this->larp('pradawne-zwoje');
        $edition = $larp->getCurrentEdition();
        self::assertNotNull($edition);
        $start = new \DateTimeImmutable('+30 days');
        $edition->setStartDate($start)->setEndDate($start->modify('+3 days'));
        $this->em()->flush();

        $crawler = $this->client->request('GET', '/larp/pradawne-zwoje');
        $link = $crawler->filter('.larp-actions a[href="/larp/pradawne-zwoje/'.$edition->getSlug().'/kalendarz.ics"]');
        self::assertCount(1, $link);
        self::assertNotNull($link->attr('download'));

        $this->client->request('GET', '/larp/pradawne-zwoje/kalendarz.ics');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/calendar; charset=utf-8');
        self::assertStringContainsString('attachment; filename=pradawne-zwoje-'.$edition->getSlug().'.ics', (string) $this->client->getResponse()->headers->get('Content-Disposition'));
        $ics = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('DTSTART;VALUE=DATE:'.$start->format('Ymd'), $ics);
        self::assertStringContainsString('SUMMARY:Pradawne Zwoje — '.$edition->getName(), $ics);

        // Initiatives have no editions to add; drafts don't exist publicly.
        $this->client->request('GET', '/larp/wyprawa-rycerska-talabeclandu/kalendarz.ics');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/larp/projekt-w-przygotowaniu/kalendarz.ics');
        self::assertResponseStatusCodeSame(404);
    }

    public function testEditorSavesSocialLinksAndTheyAreValidated(): void
    {
        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => AppFixtures::EDITOR_EMAIL]);
        self::assertNotNull($user);
        $this->client->loginUser($user);
        $larp = $this->larp('czasy-pogardy');

        $crawler = $this->client->request('GET', '/admin/larp/'.$larp->getId().'/edit');
        $form = $crawler->filter('form[name="Larp"]')->form();
        $values = $form->getPhpValues();
        $values['Larp']['socialLinks'] = [
            ['network' => 'instagram', 'url' => 'https://www.facebook.com/oops', 'label' => ''],
        ];
        $crawler = $this->client->request('POST', (string) $form->getUri(), $values);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('Instagram', $crawler->filter('.social-link-item .invalid-feedback')->text());

        $values['Larp']['socialLinks'] = [
            ['network' => 'instagram', 'url' => 'https://www.instagram.com/czasypogardy', 'label' => ''],
            ['network' => 'discord', 'url' => 'https://discord.gg/abc', 'label' => 'Discord graczy'],
        ];
        $this->client->request('POST', (string) $form->getUri(), $values);
        self::assertResponseRedirects();

        $crawler = $this->client->request('GET', '/larp/czasy-pogardy');
        self::assertSame(['Instagram', 'Discord graczy'], $crawler->filter('.larp-social a span')->each(static fn (Crawler $s): string => $s->text()));
        self::assertSelectorTextContains('.larp-social__label', 'Śledź larpa');
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
