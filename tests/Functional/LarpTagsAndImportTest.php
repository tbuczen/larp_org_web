<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\DataFixtures\AppFixtures;
use App\Entity\Character;
use App\Entity\CharacterTag;
use App\Entity\Larp;
use App\Entity\Tag;
use App\Enum\CharacterAvailability;
use App\Enum\CharacterCasting;
use App\Enum\TagType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\DomCrawler\Field\FileFormField;

final class LarpTagsAndImportTest extends WebTestCase
{
    use RollbackTransactionTrait;

    private KernelBrowser $client;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->startTransaction($this->client);
        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => AppFixtures::EDITOR_EMAIL]);
        self::assertNotNull($user);
        $this->client->loginUser($user);
    }

    protected function tearDown(): void
    {
        array_map('unlink', array_filter($this->tempFiles, 'is_file'));
        parent::tearDown();
    }

    // ---------------------------------------------------------------- LARP tags

    public function testLarpTagsScreenAddsAListAndReusesExistingNames(): void
    {
        $zwoje = $this->larp('pradawne-zwoje');
        $crawler = $this->client->request('GET', '/admin/larp/'.$zwoje->getId().'/tags');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Tagi larpa: Pradawne Zwoje');
        self::assertSelectorTextContains('.larp-tags', 'Magia');

        $form = $crawler->filter('form.larp-tags__add')->reduce(static fn (Crawler $f): bool => 'gameplay' === $f->filter('input[name="type"]')->attr('value'))->form();
        $form['tags'] = "Polowanie na smoki\nŁowy: Tropienie\n  magia  \nPolityka / intryga\n\n".str_repeat('x', 130);
        $this->client->submit($form);
        $crawler = $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-success', 'Polowanie na smoki, Tropienie');
        self::assertSelectorTextContains('.alert-info', 'Magia, Polityka / intryga');
        self::assertSelectorExists('.alert-warning');

        $own = array_map(static fn (Tag $t): string => $t->getName(), $this->em()->getRepository(Tag::class)->findBy(['larp' => $zwoje, 'type' => TagType::Gameplay], ['name' => 'ASC']));
        self::assertSame(['Magia', 'Polowanie na smoki', 'Tropienie'], $own);
        $tropienie = $this->em()->getRepository(Tag::class)->findOneBy(['name' => 'Tropienie']);
        self::assertSame('Łowy', $tropienie?->getCategory()?->getName());
    }

    public function testRemovingALarpTag(): void
    {
        $zwoje = $this->larp('pradawne-zwoje');
        $magia = $this->em()->getRepository(Tag::class)->findOneBy(['name' => 'Magia']);
        $crawler = $this->client->request('GET', '/admin/larp/'.$zwoje->getId().'/tags');
        $this->client->submit($crawler->filter('form[action$="/tags/'.$magia?->getId().'/remove"]')->form());
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-success', 'Magia');
        self::assertNull($this->em()->getRepository(Tag::class)->findOneBy(['name' => 'Magia']));
        self::assertSame('Uczeń maga Eirik', $this->character('uczen-maga-eirik')->getName(), 'characters stay, only the tag goes');
    }

    public function testInitiativesHaveNoTagsScreen(): void
    {
        $this->client->request('GET', '/admin/larp/'.$this->larp('wyprawa-rycerska-talabeclandu')->getId().'/tags');

        self::assertResponseStatusCodeSame(404);
    }

    public function testCharacterFormOffersSharedTagsAndOnlyItsOwnLarpTags(): void
    {
        $crawler = $this->client->request('GET', '/admin/character/new?'.http_build_query(['filters' => ['edition:larp' => ['comparison' => '=', 'value' => $this->larp('cybercrush')->getId()]]]));
        $gameplay = (string) $crawler->filter('[data-prototype]')->eq(1)->attr('data-prototype');

        self::assertStringContainsString('Hakowanie', $gameplay);
        self::assertStringContainsString('★ Cybercrush', $gameplay);
        self::assertStringContainsString('Komedia', $gameplay, 'shared tags too');
        self::assertStringNotContainsString('Magia', $gameplay, 'not another LARP\'s tags');
    }

    public function testAnotherLarpsTagIsRejected(): void
    {
        $decker = $this->character('decker-ghost');
        $magia = $this->em()->getRepository(Tag::class)->findOneBy(['name' => 'Magia']);
        $crawler = $this->client->request('GET', '/admin/character/'.$decker->getId().'/edit');
        $form = $crawler->filter('form[name="Character"]')->form();
        $values = $form->getPhpValues();
        $values['Character']['gameplayTags'][] = ['tag' => (string) $magia?->getId(), 'percentage' => '10'];
        $this->client->request('POST', $form->getUri(), $values);

        // The choice list doesn't even offer it; either way it must not be saved.
        self::assertResponseStatusCodeSame(422);
        self::assertNotContains('Magia', array_map(static fn ($ct) => $ct->getTag()?->getName(), $this->character('decker-ghost')->getGameplayTags()));
    }

    public function testLarpTagCannotShadowASharedTag(): void
    {
        $crawler = $this->client->request('GET', '/admin/tag/new');
        $form = $crawler->filter('form[name="Tag"]')->form();
        $form['Tag[name]'] = 'śmierć';
        $form['Tag[type]'] = 'trigger';
        $form['Tag[larp]'] = (string) $this->larp('cybercrush')->getId();
        $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Istnieje już wspólny tag „Śmierć”');
    }

    public function testMatcherGroupsLarpTagsUnderTheLarpName(): void
    {
        $crawler = $this->client->request('GET', '/larp/pradawne-zwoje/postacie');

        $groups = $crawler->filter('.matcher__category')->each(static fn (Crawler $h): string => $h->text());
        self::assertContains('Pradawne Zwoje', $groups);
        self::assertSelectorTextContains('.matcher__prefs', 'Magia');
    }

    // ------------------------------------------------------------ CSV import

    public function testImportPreviewThenConfirm(): void
    {
        $csv = "\xEF\xBB\xBFnazwa;zajawka;obsada;dostępność;rozgrywka;triggery;uwagi\n"
            ."Jarl Ulfgar;Nowa zajawka jarla.;M;zajęta;Polityka / intryga 60%; Wojna 50\n"
            ."Skald Orm;Bard z północy.;dowolna;;Relacje i więzi 70%, Śpiew 30%;\n"
            ."Wiedźma Ylva;;K;dostępna;Magia:80|Tajemnica / śledztwo=20;Opętanie 40%;notatka\n";
        $crawler = $this->upload('pradawne-zwoje', $csv);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.import-count--create', '2');
        self::assertSelectorTextContains('.import-count--update', '1');
        self::assertSelectorTextContains('.alert-info', 'uwagi');
        self::assertSelectorTextContains('.alert-warning', 'Śpiew');
        self::assertCount(0, $this->em()->getRepository(Character::class)->findBy(['slug' => 'skald-orm']), 'preview saves nothing');

        $this->client->submit($crawler->filter('form[action$="/characters/import/confirm"]')->form());
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'nowe 2, zaktualizowane 1, nowe tagi 1');

        $jarl = $this->character('jarl-ulfgar');
        self::assertSame('Nowa zajawka jarla.', $jarl->getTeaser());
        self::assertSame(CharacterAvailability::Taken, $jarl->getAvailability());
        self::assertSame(['Polityka / intryga' => 60], $this->tagMap($jarl->getGameplayTags()), 'gameplay replaced');
        self::assertSame(['Wojna' => 50], $this->tagMap($jarl->getTriggerTags()), 'triggers replaced');

        $orm = $this->character('skald-orm');
        self::assertSame(CharacterCasting::Any, $orm->getCasting());
        self::assertSame(['Relacje i więzi' => 70, 'Śpiew' => 30], $this->tagMap($orm->getGameplayTags()));
        self::assertSame('Pradawne Zwoje', $this->em()->getRepository(Tag::class)->findOneBy(['name' => 'Śpiew'])?->getLarp()?->getName());

        $ylva = $this->character('wiedzma-ylva');
        self::assertSame(CharacterCasting::Female, $ylva->getCasting());
        self::assertSame(['Magia' => 80, 'Tajemnica / śledztwo' => 20], $this->tagMap($ylva->getGameplayTags()));
    }

    public function testRowsWithErrorsBlockTheImport(): void
    {
        $crawler = $this->upload('cybercrush', "nazwa,obsada,rozgrywka\nA,kosmita,\nB,,Hakowanie 150%\nB,,\n,M,\n");

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.import-count--error', '4');
        $text = $crawler->filter('.import-table')->text();
        self::assertStringContainsString('Nieznana wartość w kolumnie „obsada”: „kosmita”', $text);
        self::assertStringContainsString('Procent dla „Hakowanie” musi być od 1 do 100', $text);
        self::assertStringContainsString('Ta sama postać jest już w wierszu 3', $text);
        self::assertStringContainsString('Brak nazwy postaci', $text);
        self::assertSelectorNotExists('form[action$="/characters/import/confirm"]');
    }

    public function testUnquotedDelimiterInsideACellIsReported(): void
    {
        $crawler = $this->upload('cybercrush', "nazwa;rozgrywka;triggery\nNowa;Hakowanie 50%; Komedia 50%;Zdrada 40%\n");

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('Więcej kolumn niż w nagłówku', $crawler->filter('.import-table')->text());
    }

    public function testATagFromTheOtherColumnIsReported(): void
    {
        $crawler = $this->upload('cybercrush', "nazwa;rozgrywka;triggery\nNowa;Hakowanie 50%;Komedia 50%\n");

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('„Komedia” to tag z drugiej kolumny', $crawler->filter('.import-table')->text());
    }

    public function testUnknownTagsAreErrorsWhenNotCreatingTags(): void
    {
        $crawler = $this->upload('cybercrush', "nazwa;rozgrywka\nNowa;Nieistniejący tag 40%\n", createTags: false);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('Nieznany tag „Nieistniejący tag”', $crawler->filter('.import-table')->text());
    }

    public function testExistingCharactersCanBeSkipped(): void
    {
        $crawler = $this->upload('cybercrush', "nazwa;zajawka\nFixer Mama Lou;Zmieniona\nNowy Runner;\n", update: false);

        self::assertSelectorTextContains('.import-count--skip', '1');
        $this->client->submit($crawler->filter('form[action$="/characters/import/confirm"]')->form());
        self::assertNotSame('Zmieniona', $this->character('fixer-mama-lou')->getTeaser());
        self::assertSame('Nowy Runner', $this->character('nowy-runner')->getName());
    }

    public function testWindows1250FileFromPolishExcel(): void
    {
        $this->upload('cybercrush', (string) iconv('UTF-8', 'WINDOWS-1250', "Nazwa;Zajawka\nŻółta Ćma;Łącznik gangu\n"));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.import-table', 'Żółta Ćma');
        self::assertSelectorTextContains('body', 'kodowanie Windows-1250');
    }

    public function testWrongFileShowsAFormError(): void
    {
        $this->upload('cybercrush', "zajawka;obsada\nx;k\n");

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Brak kolumny „nazwa”');
    }

    public function testConfirmWithAStaleTokenStartsOver(): void
    {
        $crawler = $this->upload('cybercrush', "nazwa\nNowa postać\n");
        $form = $crawler->filter('form[action$="/characters/import/confirm"]')->form();
        $form['token'] = 'wrong';
        $this->client->submit($form);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-warning', 'Podgląd wygasł');
        self::assertNull($this->em()->getRepository(Character::class)->findOneBy(['slug' => 'nowa-postac']));
    }

    public function testTemplateDownload(): void
    {
        $this->client->request('GET', '/admin/larp/'.$this->larp('pradawne-zwoje')->getId().'/characters/import/template');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/csv; charset=UTF-8');
        self::assertStringContainsString('postacie-pradawne-zwoje.csv', (string) $this->client->getResponse()->headers->get('Content-Disposition'));
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringStartsWith("\xEF\xBB\xBFnazwa;zajawka;opis;obsada;dostępność;kolejność;rozgrywka;triggery", $content);
    }

    public function testTheTemplateImportsCleanly(): void
    {
        $this->client->request('GET', '/admin/larp/'.$this->larp('pradawne-zwoje')->getId().'/characters/import/template');
        $this->upload('pradawne-zwoje', (string) $this->client->getResponse()->getContent());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.import-count--create', '2');
        self::assertSelectorTextContains('.import-count--error', '0');
    }

    private function upload(string $larpSlug, string $csv, bool $update = true, bool $createTags = true): Crawler
    {
        $file = tempnam(sys_get_temp_dir(), 'import').'.csv';
        file_put_contents($file, $csv);
        $this->tempFiles[] = $file;

        $crawler = $this->client->request('GET', '/admin/larp/'.$this->larp($larpSlug)->getId().'/characters/import');
        $form = $crawler->filter('form[name="form"]')->form();
        $field = $form['form[file]'];
        self::assertInstanceOf(FileFormField::class, $field);
        $field->upload($file);
        foreach (['updateExisting' => $update, 'createTags' => $createTags] as $name => $on) {
            $checkbox = $form['form['.$name.']'];
            self::assertInstanceOf(ChoiceFormField::class, $checkbox);
            $on ? $checkbox->tick() : $checkbox->untick();
        }

        return $this->client->submit($form);
    }

    /**
     * @param list<CharacterTag> $tags
     *
     * @return array<string, int>
     */
    private function tagMap(array $tags): array
    {
        $map = [];
        foreach ($tags as $ct) {
            $map[(string) $ct->getTag()?->getName()] = $ct->getPercentage();
        }

        return $map;
    }

    private function em(): EntityManagerInterface
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        return $em;
    }

    private function larp(string $slug): Larp
    {
        return $this->em()->getRepository(Larp::class)->findOneBy(['slug' => $slug]) ?? throw new \RuntimeException($slug);
    }

    private function character(string $slug): Character
    {
        return $this->em()->getRepository(Character::class)->findOneBy(['slug' => $slug]) ?? throw new \RuntimeException($slug);
    }
}
