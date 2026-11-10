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

final class LarpColorsTest extends WebTestCase
{
    use RollbackTransactionTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->startTransaction($this->client);
    }

    public function testLarpPagesAndCardsUseTheLarpsColours(): void
    {
        foreach (['/larp/cybercrush', '/larp/cybercrush/postacie'] as $url) {
            $crawler = $this->client->request('GET', $url);
            self::assertResponseIsSuccessful();
            $style = $crawler->filter('head style')->reduce(static fn (Crawler $s): bool => str_contains($s->text(), ':root'));
            self::assertCount(1, $style, $url);
            self::assertStringContainsString('--color-bg: #07070f', $style->text());
            self::assertStringContainsString('--color-primary: #ff3fd2', $style->text());
        }

        $crawler = $this->client->request('GET', '/larpy?minione=1');
        $card = $crawler->filter('.event-card')->reduce(static fn (Crawler $c): bool => str_contains($c->text(), 'Cybercrush'));
        self::assertStringContainsString('larp-card--colored', (string) $card->attr('class'));
        self::assertStringContainsString('--color-bg: #07070f', (string) $card->attr('style'));
        self::assertSelectorNotExists('head style', 'pages that are not about one LARP keep the theme');
    }

    public function testRemovingTheColoursRevertsToTheTheme(): void
    {
        $larp = $this->em()->getRepository(Larp::class)->findOneBy(['slug' => 'cybercrush']);
        self::assertInstanceOf(Larp::class, $larp);
        $larp->setColorPreset(null);
        $this->em()->flush();

        $crawler = $this->client->request('GET', '/larp/cybercrush');
        self::assertSelectorNotExists('head style');
        $crawler = $this->client->request('GET', '/larpy?minione=1');
        $card = $crawler->filter('.event-card')->reduce(static fn (Crawler $c): bool => str_contains($c->text(), 'Cybercrush'));
        self::assertNull($card->attr('style'));
    }

    public function testEditorPicksColoursAndIsWarnedAboutLowContrast(): void
    {
        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => AppFixtures::EDITOR_EMAIL]);
        self::assertNotNull($user);
        $this->client->loginUser($user);
        $larp = $this->em()->getRepository(Larp::class)->findOneBy(['slug' => 'vault-47']);
        self::assertInstanceOf(Larp::class, $larp);

        $crawler = $this->client->request('GET', '/admin/larp/'.$larp->getId().'/edit');
        self::assertSelectorExists('select[name="Larp[colorPreset]"][data-color-presets]');
        $form = $crawler->filter('form[name="Larp"]')->form();
        $values = $form->getPhpValues();

        $values['Larp']['colorText'] = 'zielony';
        $crawler = $this->client->request('POST', (string) $form->getUri(), $values);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('#rrggbb', $crawler->filter('.invalid-feedback')->text());

        $values['Larp']['colorText'] = '#3A3028';
        $this->client->request('POST', (string) $form->getUri(), $values);
        self::assertResponseRedirects();
        $crawler = $this->client->followRedirect();
        self::assertStringContainsString('słabo czytelny', $crawler->filter('.alert-warning')->text(), 'saved, with a warning');

        $crawler = $this->client->request('GET', '/larp/vault-47');
        self::assertStringContainsString('--color-text: #3a3028', $crawler->filter('head style')->text());
    }

    private function em(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
