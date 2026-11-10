<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\DataFixtures\AppFixtures;
use App\Entity\Gallery;
use App\Entity\Larp;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class EditionPagesTest extends WebTestCase
{
    use RollbackTransactionTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->startTransaction($this->client);
    }

    public function testLarpPageLinksEveryEditionWithItsCharactersAndGalleries(): void
    {
        $this->gallery('Zwoje 2025 — bitwa', '2-edycja', 'https://photos.app.goo.gl/x');

        $crawler = $this->client->request('GET', '/larp/pradawne-zwoje');
        $cards = $crawler->filter('.larp-editions .edition-card');
        self::assertSame(['3. edycja', '2. edycja', '1. edycja'], $cards->filter('.edition-card__title')->each(static fn (Crawler $c): string => trim($c->text())), 'newest first');
        self::assertSame('/larp/pradawne-zwoje/3-edycja', $cards->first()->filter('a')->first()->attr('href'));
        self::assertStringContainsString('edition-card--current', (string) $cards->first()->attr('class'));
        self::assertStringContainsString('6 postaci', $cards->eq(0)->text());
        self::assertStringContainsString('3 postacie · 1 galeria', $cards->eq(1)->text());
        self::assertSame('/larp/pradawne-zwoje/3-edycja/postacie', $crawler->filter('.larp-characters .section-head a')->attr('href'));
    }

    public function testPastEditionPageShowsItsOwnCharactersAndGalleries(): void
    {
        $this->gallery('Zwoje 2025 — bitwa', '2-edycja', 'https://photos.app.goo.gl/x');
        $this->gallery('Zwoje 2026', '3-edycja', 'https://photos.app.goo.gl/y');

        $crawler = $this->client->request('GET', '/larp/pradawne-zwoje/2-edycja');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Pradawne Zwoje');
        self::assertSelectorTextContains('h1 .larp-hero__edition', '2. edycja');
        self::assertSelectorTextContains('.facts', '7–10.VIII.2025');
        self::assertSelectorNotExists('.larp-hero .badge--open', 'no recruitment on a past edition');
        self::assertSelectorNotExists('.larp-actions', 'no event or calendar for it');

        self::assertSame(['Jarl Sigrun', 'Łowca smoków Bjorn', 'Skald Eydis'], $crawler->filter('.larp-characters .character-card h3')->each(static fn (Crawler $c): string => trim($c->text())));
        self::assertSame('/larp/pradawne-zwoje/2-edycja/postac/jarl-sigrun', $crawler->filter('.larp-characters .character-card h3 a')->first()->attr('href'));
        self::assertStringContainsString('Przeglądaj 3 postacie', $crawler->filter('.larp-characters .section-head a')->text());
        $galleries = $crawler->filter('.larp-galleries .gallery-card__title');
        self::assertCount(1, $galleries, "only this edition's galleries");
        self::assertStringStartsWith('Zwoje 2025 — bitwa', trim($galleries->text()));
        self::assertSame('2. edycja 2025', trim($crawler->filter('.edition-nav a[aria-current="page"]')->text()));

        // Its characters, with the shared tags of the LARP.
        $crawler = $this->client->request('GET', '/larp/pradawne-zwoje/2-edycja/postac/lowca-smokow-bjorn');
        self::assertResponseIsSuccessful();
        $tags = implode(' | ', $crawler->filter('.tag-bar')->each(static fn (Crawler $c): string => $c->text()));
        self::assertStringContainsString('Smoki', $tags, "the LARP's own trigger, shared by its editions");
        self::assertStringContainsString('Magia', $tags);

        $crawler = $this->client->request('GET', '/larp/pradawne-zwoje/2-edycja/postacie');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.notice', 'już się odbyła');
        self::assertCount(3, $crawler->filter('.match-card'));
    }

    public function testCurrentEditionPageHasRecruitmentAndEvent(): void
    {
        $crawler = $this->client->request('GET', '/larp/pradawne-zwoje/3-edycja');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.larp-hero .badge--open');
        self::assertSelectorExists('.larp-actions a[href="https://fb.me/e/hlDppZ9HB"]');
        self::assertCount(6, $crawler->filter('.larp-characters .character-card'));
    }

    public function testEditionAddressesAreGeneratedAndChecked(): void
    {
        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => AppFixtures::EDITOR_EMAIL]);
        self::assertNotNull($user);
        $this->client->loginUser($user);
        $edition = $this->larp('vault-47')->getEditionBySlug('1-edycja');
        self::assertNotNull($edition);
        $url = '/admin/larp-edition/'.$edition->getId().'/edit';

        $crawler = $this->client->request('GET', $url);
        $form = $crawler->filter('form[name="LarpEdition"]')->form();
        $values = $form->getPhpValues();

        $values['LarpEdition']['slug'] = 'postacie';
        $crawler = $this->client->request('POST', $url, $values);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('zajęty', $crawler->filter('.invalid-feedback')->text());

        $values['LarpEdition']['slug'] = '2-edycja';
        $crawler = $this->client->request('POST', $url, $values);
        self::assertResponseStatusCodeSame(422, 'another edition of Vault 47 has it');
        self::assertStringContainsString('ma już ten adres', $crawler->filter('.invalid-feedback')->text());

        $values['LarpEdition']['slug'] = 'pierwsza-krypta';
        $this->client->request('POST', $url, $values);
        self::assertResponseRedirects();
        $this->client->request('GET', '/larp/vault-47/pierwsza-krypta');
        self::assertResponseIsSuccessful();

        // A new edition gets its address from its name.
        $larp = $this->larp('vault-47');
        $crawler = $this->client->request('GET', '/admin/larp-edition/new?'.http_build_query(['filters' => ['larp' => ['comparison' => '=', 'value' => $larp->getId()]]]));
        $form = $crawler->filter('form[name="LarpEdition"]')->form();
        $values = $form->getPhpValues();
        $values['LarpEdition']['name'] = 'Finał';
        $values['LarpEdition']['recruitment'] = 'not_started';
        $this->client->request('POST', (string) $form->getUri(), $values);
        self::assertResponseRedirects();
        $this->client->request('GET', '/larp/vault-47/final');
        self::assertResponseIsSuccessful();
    }

    public function testEachEditionHasItsOwnImageAndTexts(): void
    {
        $crawler = $this->client->request('GET', '/larp/pradawne-zwoje/2-edycja');
        self::assertSelectorTextContains('.larp-hero .lead', 'Oblężenie twierdzy jarl Sigrun.');
        self::assertSelectorTextContains('.larp-page .prose', 'odcięta przez śnieżycę');
        self::assertSame('Oblężenie twierdzy jarl Sigrun.', $crawler->filter('meta[name="description"]')->attr('content'));

        // Without its own texts, an edition shows the LARP's.
        $this->client->request('GET', '/larp/vault-47/1-edycja');
        self::assertSelectorTextContains('.larp-hero .lead', 'Larp w świecie Fallouta');

        // An edition image replaces the LARP's on its page, its card and (when current) the LARP page.
        $edition = $this->larp('pradawne-zwoje')->getEditionBySlug('3-edycja');
        self::assertNotNull($edition);
        $edition->setCoverImage('plakat-2026.jpg');
        $this->em()->flush();
        $crawler = $this->client->request('GET', '/larp/pradawne-zwoje/3-edycja');
        self::assertSame('/uploads/editions/plakat-2026.jpg', $crawler->filter('.larp-hero img.cover')->attr('src'));
        $crawler = $this->client->request('GET', '/larp/pradawne-zwoje');
        self::assertSame('/uploads/editions/plakat-2026.jpg', $crawler->filter('.larp-hero img.cover')->attr('src'));
        self::assertSame('/uploads/editions/plakat-2026.jpg', $crawler->filter('.edition-card--current img')->attr('src'));
        $crawler = $this->client->request('GET', '/larpy?minione=1');
        $card = $crawler->filter('.event-card')->reduce(static fn (Crawler $c): bool => str_contains($c->text(), 'Pradawne Zwoje') && str_contains($c->text(), '3. edycja'));
        self::assertSame('/uploads/editions/plakat-2026.jpg', $card->filter('img.cover')->attr('src'));
        self::assertStringContainsString('Nowy jarl, stare długi', $card->text(), "the current edition's teaser");
    }

    private function gallery(string $title, string $edition, string $url): void
    {
        $run = $this->larp('pradawne-zwoje')->getEditionBySlug($edition);
        self::assertNotNull($run);
        $this->em()->persist((new Gallery())->setTitle($title)->setEdition($run)->setExternalUrl($url)->setPublished(true));
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
