<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\DataFixtures\AppFixtures;
use App\Entity\Character;
use App\Entity\Larp;
use App\Entity\Tag;
use App\Entity\TagCategory;
use App\Enum\TagType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Field\FileFormField;

final class AdminLarpTest extends WebTestCase
{
    use RollbackTransactionTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->startTransaction($this->client);
        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => AppFixtures::EDITOR_EMAIL]);
        self::assertNotNull($user);
        $this->client->loginUser($user);
    }

    /** @return iterable<string, array{string}> */
    public static function adminPages(): iterable
    {
        foreach (['larp', 'character', 'tag', 'tag-category'] as $crud) {
            yield $crud.' index' => ['/admin/'.$crud];
            if ('character' !== $crud) { // characters are added from a LARP (see below)
                yield $crud.' new' => ['/admin/'.$crud.'/new'];
            }
        }
    }

    #[DataProvider('adminPages')]
    public function testEditorCanOpenPage(string $url): void
    {
        $this->client->request('GET', $url);

        self::assertResponseIsSuccessful();
    }

    public function testEditPagesOpen(): void
    {
        $larp = $this->larp('pradawne-zwoje');
        $this->client->request('GET', '/admin/larp/'.$larp->getId().'/edit');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[name^="Larp[editions]"]');

        $character = $this->character('kaplanka-mary');
        $this->client->request('GET', '/admin/character/'.$character->getId().'/edit');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(3, '#Character_triggerTags .character-tag-item');
        self::assertSelectorCount(3, '#Character_gameplayTags .character-tag-item');
    }

    public function testCharactersActionFiltersByLarp(): void
    {
        $crawler = $this->client->request('GET', '/admin/larp');
        $link = $crawler->filter('tr')
            ->reduce(static fn ($row): bool => str_contains($row->text(), 'Cybercrush'))
            ->filter('a.action-characters');
        self::assertCount(1, $link, 'Cybercrush row has a "Characters" action');

        $this->client->click($link->link());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'Fixer Mama Lou');
        self::assertSelectorTextNotContains('table', 'Kapłanka Mary');

        // "Add character" from the filtered list pre-selects that LARP's current edition.
        $this->client->click($this->client->getCrawler()->filter('a.action-new')->link());
        $edition = $this->larp('cybercrush')->getCurrentEdition();
        self::assertSame((string) $edition?->getId(), $this->client->getCrawler()->filter('#Character_edition option[selected]')->attr('value'));
    }

    public function testCreateLarpWithEdition(): void
    {
        $crawler = $this->client->request('GET', '/admin/larp/new');
        $form = $crawler->filter('form[name="Larp"]')->form();
        $values = $form->getPhpValues();
        $values['Larp']['name'] = 'Nowy Larp Testowy';
        $values['Larp']['slug'] = '';
        $values['Larp']['status'] = 'current';
        $values['Larp']['editions'] = [0 => [
            'name' => '1. edycja',
            'startDate' => '2027-05-01',
            'endDate' => '2027-05-03',
            'recruitment' => 'open',
        ]];
        $this->client->request('POST', $form->getUri(), $values);
        self::assertResponseRedirects();

        $larp = $this->larp('nowy-larp-testowy');
        self::assertSame(1, $larp->getEditionCount());
        self::assertSame('2027-05-01', $larp->getCurrentEdition()?->getStartDate()?->format('Y-m-d'));

        $this->client->request('GET', '/larp/nowy-larp-testowy');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.facts', '1–3.V.2027');
    }

    public function testCoverImageUpload(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'cover').'.png';
        $image = imagecreatetruecolor(32, 18);
        imagepng($image, $file);

        $larp = $this->larp('cybercrush');
        $crawler = $this->client->request('GET', '/admin/larp/'.$larp->getId().'/edit');
        $form = $crawler->filter('form[name="Larp"]')->form();
        $upload = $form['Larp[coverImage][file]'];
        self::assertInstanceOf(FileFormField::class, $upload);
        $upload->upload($file);
        $this->client->submit($form);
        self::assertResponseRedirects();

        $stored = $this->larp('cybercrush')->getCoverImage();
        self::assertNotNull($stored);
        $path = self::getContainer()->getParameter('kernel.project_dir').'/public/uploads/larps/'.$stored;
        self::assertFileExists($path);

        $this->client->request('GET', '/larp/cybercrush');
        self::assertSelectorExists('img.cover[src$="'.$stored.'"]');

        unlink($path);
        unlink($file);
    }

    public function testEditionEndBeforeStartIsRejected(): void
    {
        $crawler = $this->client->request('GET', '/admin/larp/new');
        $form = $crawler->filter('form[name="Larp"]')->form();
        $values = $form->getPhpValues();
        $values['Larp']['name'] = 'Zle daty';
        $values['Larp']['editions'] = [0 => ['name' => 'x', 'startDate' => '2027-05-03', 'endDate' => '2027-05-01', 'recruitment' => 'open']];
        $this->client->request('POST', $form->getUri(), $values);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Koniec nie może być przed początkiem.');
    }

    public function testCreateCharacterWithTriggerAndGameplayTags(): void
    {
        $edition = $this->larp('cybercrush')->getCurrentEdition();
        self::assertNotNull($edition);

        $crawler = $this->client->request('GET', $this->newCharacterUrl('cybercrush'));
        $form = $crawler->filter('form[name="Character"]')->form();
        $values = $form->getPhpValues();
        $values['Character']['name'] = 'Netrunnerka Vex';
        $values['Character']['slug'] = '';
        $values['Character']['edition'] = (string) $edition->getId();
        $values['Character']['triggerTags'] = [
            0 => ['tag' => (string) $this->tag('Broń palna')->getId(), 'percentage' => '40'],
        ];
        $values['Character']['gameplayTags'] = [
            0 => ['tag' => (string) $this->tag('Tajemnica / śledztwo')->getId(), 'percentage' => '70'],
            1 => ['tag' => (string) $this->tag('Konflikt / walka')->getId(), 'percentage' => '30'],
        ];
        $this->client->request('POST', $form->getUri(), $values);
        // Back to Cybercrush's character list, not the LARP picker.
        self::assertResponseRedirects();
        self::assertStringContainsString('edition:larp', urldecode((string) $this->client->getResponse()->headers->get('Location')));

        $character = $this->character('netrunnerka-vex');
        self::assertSame(['Broń palna'], array_map(static fn ($ct) => $ct->getTag()?->getName(), $character->getTriggerTags()));
        self::assertSame([70, 30], array_map(static fn ($ct) => $ct->getPercentage(), $character->getGameplayTags()));
    }

    public function testCharacterListStartsWithALarpPicker(): void
    {
        $crawler = $this->client->request('GET', '/admin/character');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Wybierz larpa');
        $names = $crawler->filter('.larp-picker__main strong')->each(static fn ($n): string => $n->text());
        self::assertContains('Pradawne Zwoje', $names);
        self::assertContains('Vault 47', $names);
        self::assertNotContains('Wyprawa Rycerska Talabeclandu', $names, 'initiatives have no characters');
        $zwoje = $crawler->filter('.larp-picker__item')->reduce(static fn ($i): bool => str_contains($i->text(), 'Pradawne Zwoje'));
        self::assertStringContainsString('10 postaci', $zwoje->text(), 'all editions');

        $this->client->click($crawler->filter('.larp-picker__item')->reduce(static fn ($i): bool => str_contains($i->text(), 'Cybercrush'))->selectLink('Dodaj postać')->link());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Nowa postać: Cybercrush');
    }

    public function testNewCharacterWithoutLarpGoesToThePicker(): void
    {
        $this->client->request('GET', '/admin/character/new');

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Wybierz larpa');
    }

    public function testEditionChoicesAreLimitedToTheLarp(): void
    {
        $crawler = $this->client->request('GET', $this->newCharacterUrl('pradawne-zwoje'));

        $options = $crawler->filter('#Character_edition option')->each(static fn ($o): string => $o->text());
        $options = array_values(array_filter($options, static fn (string $o): bool => '' !== trim($o)));
        self::assertSame(['3. edycja · 6–9.VIII.2026', '2. edycja · 7–10.VIII.2025', '1. edycja · 8–11.VIII.2024'], $options);
        self::assertSame('3. edycja · 6–9.VIII.2026', $crawler->filter('#Character_edition option[selected]')->text(), 'current edition preselected');

        $character = $this->character('jarl-ulfgar');
        $crawler = $this->client->request('GET', '/admin/character/'.$character->getId().'/edit');
        self::assertCount(3, $crawler->filter('#Character_edition option[value]:not([value=""])'));
        self::assertSelectorTextContains('h1', 'Jarl Ulfgar');
    }

    public function testLarpWithoutEditionsAsksForOneFirst(): void
    {
        $larp = (new Larp())->setName('Bez edycji')->setSlug('bez-edycji');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($larp);
        $em->flush();

        $this->client->request('GET', $this->newCharacterUrl('bez-edycji'));

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert', 'nie ma jeszcze edycji');
    }

    public function testDuplicateTagIsRejected(): void
    {
        $crawler = $this->client->request('GET', $this->newCharacterUrl('cybercrush'));
        $form = $crawler->filter('form[name="Character"]')->form();
        $values = $form->getPhpValues();
        $values['Character']['name'] = 'Duplikat';
        $values['Character']['edition'] = (string) $this->larp('cybercrush')->getCurrentEdition()?->getId();
        $tagId = (string) $this->tag('Śmierć')->getId();
        $values['Character']['triggerTags'] = [
            0 => ['tag' => $tagId, 'percentage' => '40'],
            1 => ['tag' => $tagId, 'percentage' => '60'],
        ];
        $this->client->request('POST', $form->getUri(), $values);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Tag „Śmierć” został dodany więcej niż raz.');
    }

    public function testPercentageOutOfRangeIsRejected(): void
    {
        $crawler = $this->client->request('GET', $this->newCharacterUrl('cybercrush'));
        $form = $crawler->filter('form[name="Character"]')->form();
        $values = $form->getPhpValues();
        $values['Character']['name'] = 'Za dużo';
        $values['Character']['edition'] = (string) $this->larp('cybercrush')->getCurrentEdition()?->getId();
        $values['Character']['gameplayTags'] = [0 => ['tag' => (string) $this->tag('Komedia')->getId(), 'percentage' => '150']];
        $this->client->request('POST', $form->getUri(), $values);

        self::assertResponseStatusCodeSame(422);
    }

    public function testTriggerListOnlyOffersTriggerTags(): void
    {
        $crawler = $this->client->request('GET', $this->newCharacterUrl('cybercrush'));
        // The first collection on the form is the trigger list, the second the gameplay list.
        $prototype = (string) $crawler->filter('[data-prototype]')->eq(0)->attr('data-prototype');

        self::assertStringContainsString('Śmierć', $prototype);
        self::assertStringNotContainsString('Komedia', $prototype);
        self::assertStringContainsString('<optgroup label="Śmierć i żałoba"', $prototype);
    }

    public function testTagCategoryMustMatchTagType(): void
    {
        $crawler = $this->client->request('GET', '/admin/tag/new');
        $form = $crawler->filter('form[name="Tag"]')->form();
        $form['Tag[name]'] = 'Nowy tag';
        $form['Tag[type]'] = 'gameplay';
        $categoryId = $this->em()->getRepository(TagCategory::class)->findOneBy(['type' => TagType::Trigger])?->getId();
        $form['Tag[category]'] = (string) $categoryId;
        $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Kategoria ma inny typ niż tag.');
    }

    private function newCharacterUrl(string $larpSlug): string
    {
        return '/admin/character/new?'.http_build_query(['filters' => ['edition:larp' => ['comparison' => '=', 'value' => $this->larp($larpSlug)->getId()]]]);
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

    private function tag(string $name): Tag
    {
        return $this->em()->getRepository(Tag::class)->findOneBy(['name' => $name]) ?? throw new \RuntimeException($name);
    }
}
