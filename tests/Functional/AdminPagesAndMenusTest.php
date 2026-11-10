<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\DataFixtures\AppFixtures;
use App\Entity\Menu;
use App\Entity\Page;
use App\Entity\PageBlock;
use App\Entity\SiteSettings;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class AdminPagesAndMenusTest extends WebTestCase
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

    public function testLayoutScreenListsBlocksAndBlockTypes(): void
    {
        $crawler = $this->client->request('GET', '/admin/page/'.$this->home()->getId().'/layout');

        self::assertResponseIsSuccessful();
        self::assertCount(7, $crawler->filter('.block-list > .block-item'));
        self::assertCount(11, $crawler->filter('.block-types button'));
        self::assertSelectorExists('[data-sortable-url][data-csrf-token]');
    }

    public function testAddEditAndRemoveABlock(): void
    {
        $crawler = $this->client->request('GET', '/admin/page/'.$this->home()->getId().'/layout');
        $form = $crawler->filter('form[action$="/blocks/add/cta"]')->form();
        $this->client->submit($form);
        self::assertResponseRedirects();

        // Lands on the new block's settings form.
        $crawler = $this->client->followRedirect();
        $form = $crawler->filter('form[name="PageBlock"]')->form();
        $form['PageBlock[settings][title]'] = 'Rekrutacja na Pradawne Zwoje trwa!';
        $form['PageBlock[settings][button_label]'] = 'Zapisz się';
        $form['PageBlock[settings][button_url]'] = 'https://fb.me/e/69mffUNUK';
        $this->client->submit($form);
        self::assertResponseRedirects();

        $this->client->request('GET', '/');
        self::assertSelectorTextContains('.block--cta .cta__title', 'Rekrutacja na Pradawne Zwoje trwa!');
        self::assertSelectorTextContains('.site-main > :last-child', 'Rekrutacja', 'added at the end');

        $block = $this->em()->getRepository(PageBlock::class)->findOneBy(['type' => 'cta']);
        self::assertNotNull($block);
        $crawler = $this->client->request('GET', '/admin/page/'.$this->home()->getId().'/layout');
        $this->client->submit($crawler->filter('form[action$="/page-block/'.$block->getId().'/remove"]')->form());
        $this->client->request('GET', '/');
        self::assertSelectorNotExists('.block--cta');
    }

    public function testReorderToggleAndMove(): void
    {
        $home = $this->home();
        $blocks = $home->getBlocks()->toArray();
        $crawler = $this->client->request('GET', '/admin/page/'.$home->getId().'/layout');
        $list = $crawler->filter('[data-sortable-url]');

        // Drag and drop: social links first.
        $ids = array_map(static fn (PageBlock $b): int => (int) $b->getId(), $blocks);
        $last = array_pop($ids);
        array_unshift($ids, $last);
        $this->client->request('POST', (string) $list->attr('data-sortable-url'), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => (string) $list->attr('data-csrf-token'),
        ], content: (string) json_encode(['ids' => $ids]));
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/');
        self::assertSelectorExists('.site-main > section.block--social-links:first-child');

        // Without a valid token nothing changes.
        $this->client->request('POST', (string) $list->attr('data-sortable-url'), server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => 'wrong'], content: '{"ids":[]}');
        self::assertResponseStatusCodeSame(403);

        // Hide the hero.
        $hero = $this->em()->getRepository(PageBlock::class)->findOneBy(['type' => 'hero', 'page' => $home]);
        $crawler = $this->client->request('GET', '/admin/page/'.$home->getId().'/layout');
        $this->client->submit($crawler->filter('form[action$="/page-block/'.$hero?->getId().'/toggle"]')->form());
        $this->client->request('GET', '/');
        self::assertSelectorNotExists('.block--hero');

        // Move the social block back down one step.
        $social = $this->em()->getRepository(PageBlock::class)->findOneBy(['type' => 'social_links', 'page' => $home]);
        $crawler = $this->client->request('GET', '/admin/page/'.$home->getId().'/layout');
        $this->client->submit($crawler->filter('form[action$="/page-block/'.$social?->getId().'/move/down"]')->form());
        $positions = array_map(static fn (PageBlock $b): string => $b->getType(), $this->sortedBlocks());
        self::assertSame(['hero', 'social_links'], \array_slice($positions, 0, 2));
    }

    public function testEveryBlockTypeCanBeAddedAndRendered(): void
    {
        $page = (new Page())->setTitle('Test bloków')->setSlug('test-blokow');
        $this->em()->persist($page);
        $this->em()->flush();

        $crawler = $this->client->request('GET', '/admin/page/'.$page->getId().'/layout');
        $types = $crawler->filter('.block-types form')->each(static fn (Crawler $f): string => (string) $f->attr('action'));
        foreach ($types as $action) {
            $crawler = $this->client->request('GET', '/admin/page/'.$page->getId().'/layout');
            $this->client->submit($crawler->filter('form[action="'.$action.'"]')->form());
            self::assertResponseRedirects();
            $this->client->followRedirect();
            self::assertResponseIsSuccessful(sprintf('Settings form for %s', $action));
        }

        $this->client->request('GET', '/test-blokow');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.block--hero');
        self::assertSelectorExists('.block--larp-showcase');
        self::assertSelectorExists('.block--matcher-teaser');
    }

    public function testMenuItemsScreenAndNewItem(): void
    {
        $menu = $this->em()->getRepository(Menu::class)->findOneBy(['menuKey' => 'main']);
        self::assertNotNull($menu);
        $crawler = $this->client->request('GET', '/admin/menu/'.$menu->getId().'/items');
        self::assertResponseIsSuccessful();
        self::assertCount(6, $crawler->filter('.block-list:not(.block-list--nested) > .block-item'));
        self::assertCount(2, $crawler->filter('.block-list--nested'));

        $crawler = $this->client->click($crawler->filter('.page-actions a')->link());
        $form = $crawler->filter('form[name="MenuItem"]')->form();
        $form['MenuItem[label]'] = 'Discord';
        $form['MenuItem[target]'] = 'url';
        $form['MenuItem[url]'] = 'https://discord.gg/Zjf2mZN5jV';
        $newTab = $form['MenuItem[newTab]'];
        self::assertInstanceOf(ChoiceFormField::class, $newTab);
        $newTab->tick();
        $this->client->submit($form);
        self::assertResponseRedirects();

        $crawler = $this->client->request('GET', '/');
        $link = $crawler->filter('.site-nav a[href="https://discord.gg/Zjf2mZN5jV"]');
        self::assertCount(1, $link);
        self::assertSame('_blank', $link->attr('target'));
    }

    public function testMenuItemNeedsATarget(): void
    {
        $menu = $this->em()->getRepository(Menu::class)->findOneBy(['menuKey' => 'main']);
        $crawler = $this->client->request('GET', '/admin/menu-item/new?menu='.$menu?->getId());
        $form = $crawler->filter('form[name="MenuItem"]')->form();
        $form['MenuItem[label]'] = 'Pusty link';
        $form['MenuItem[target]'] = 'page';
        $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Wybierz, dokąd prowadzi ten link.');
    }

    public function testSwitchingThemeChangesThePublicSite(): void
    {
        $settings = $this->em()->getRepository(SiteSettings::class)->findOneBy([]);
        $crawler = $this->client->request('GET', '/admin/site-settings/'.$settings?->getId().'/edit');
        $form = $crawler->filter('form[name="SiteSettings"]')->form();
        $theme = $form['SiteSettings[theme]'];
        self::assertInstanceOf(ChoiceFormField::class, $theme);
        self::assertContains('base', $theme->availableOptionValues());
        self::assertContains('lastajemnic', $theme->availableOptionValues());
        $form['SiteSettings[theme]'] = 'base';
        $this->client->submit($form);

        $crawler = $this->client->request('GET', '/');
        self::assertSelectorExists('html[data-theme="base"]');
        self::assertCount(0, $crawler->filter('link[href*="themes/lastajemnic"]'));
    }

    public function testGalleryUploadsSeveralPhotosAndKeepsAddingToThem(): void
    {
        $page = (new Page())->setTitle('Galeria')->setSlug('galeria-test');
        $this->em()->persist($page);
        $this->em()->flush();
        $stored = [];

        try {
            $crawler = $this->client->request('GET', '/admin/page/'.$page->getId().'/layout');
            $this->client->submit($crawler->filter('form[action$="/blocks/add/gallery"]')->form());
            $crawler = $this->client->followRedirect();

            $form = $crawler->filter('form[name="PageBlock"]')->form();
            $form['PageBlock[settings][title]'] = 'Z naszych larpów';
            $form['PageBlock[settings][captions]'] = "Obozowisko\n\nPatrol";
            $form['PageBlock[settings][layout]'] = 'drift';
            $this->client->request('POST', (string) $form->getUri(), $form->getPhpValues(), [
                'PageBlock' => ['settings' => ['images' => ['file' => [$this->png('a'), $this->png('b')]]]],
            ]);
            self::assertResponseRedirects();

            $block = $this->em()->getRepository(PageBlock::class)->findOneBy(['type' => 'gallery']);
            self::assertNotNull($block);
            $id = $block->getId();
            $stored = $this->galleryBlock($id)->getSettings()['images'];
            self::assertCount(2, $stored);

            // A second upload adds to the photos already there.
            $crawler = $this->client->request('GET', '/admin/page-block/'.$id.'/edit');
            $form = $crawler->filter('form[name="PageBlock"]')->form();
            $this->client->request('POST', (string) $form->getUri(), $form->getPhpValues(), [
                'PageBlock' => ['settings' => ['images' => ['file' => [$this->png('c')]]]],
            ]);
            self::assertResponseRedirects();
            $stored = $this->galleryBlock($id)->getSettings()['images'];
            self::assertCount(3, $stored);
            foreach ($stored as $file) {
                self::assertFileExists(self::uploadDir().'/'.$file);
            }

            $crawler = $this->client->request('GET', '/galeria-test');
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('.block--gallery.gallery--drift[data-controller~="lightbox"][data-controller~="scroll-fx"]');
            self::assertCount(2, $crawler->filter('.gallery__row'));
            // 3 photos per row, the rest are copies that fill the strip, hidden from screen readers.
            $row = $crawler->filter('.gallery__row')->first();
            self::assertCount(3, $row->filter('.gallery__item:not(.gallery__item--clone)'));
            self::assertCount(0, $row->filter('.gallery__item--clone:not([aria-hidden="true"])'));
            self::assertGreaterThan(3, $row->filter('.gallery__item--clone')->count());
            self::assertSame('Obozowisko', $row->filter('.gallery__item:not(.gallery__item--clone) .gallery__caption')->first()->text());

            /** @var list<array{src: string, caption: string}> $photos */
            $photos = json_decode((string) $crawler->filter('.block--gallery')->attr('data-lightbox-photos-value'), true);
            self::assertCount(3, $photos);
            self::assertSame(['Obozowisko', '', 'Patrol'], array_column($photos, 'caption'));
            self::assertStringContainsString('/uploads/blocks/'.$stored[0], $photos[0]['src']);

            // Other layouts render too.
            foreach (['scroll' => '.gallery__row', 'parallax' => '.gallery__column', 'grid' => '.gallery__grid'] as $layout => $selector) {
                $block = $this->galleryBlock($id);
                $block->setSettings(['layout' => $layout] + $block->getSettings());
                $this->em()->flush();
                $this->client->request('GET', '/galeria-test');
                self::assertSelectorExists('.gallery--'.$layout.' '.$selector, $layout);
            }
        } finally {
            foreach ($stored as $file) {
                @unlink(self::uploadDir().'/'.$file);
            }
        }
    }

    public function testParallaxBannerFallsBackToTheThemeIllustration(): void
    {
        $page = (new Page())->setTitle('Baner')->setSlug('baner-test');
        $page->addBlock(new PageBlock('parallax_banner', ['title' => 'Każda historia zaczyna się w lesie', 'eyebrow' => 'Las Tajemnic', 'height' => 'large', 'align' => 'center']));
        $this->em()->persist($page);
        $this->em()->flush();

        $this->client->request('GET', '/baner-test');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.block--parallax-banner.pbanner--center.pbanner--large[data-controller="scroll-fx"]');
        self::assertSelectorExists('.pbanner__image[src*="themes/lastajemnic/assets/images/hero"]');
        self::assertSelectorTextContains('.pbanner__title', 'Każda historia zaczyna się w lesie');
    }

    private function galleryBlock(?int $id): PageBlock
    {
        $this->em()->clear();
        $block = $this->em()->find(PageBlock::class, $id);
        self::assertInstanceOf(PageBlock::class, $block);

        return $block;
    }

    private static function uploadDir(): string
    {
        return \dirname(__DIR__, 2).'/public/uploads/blocks';
    }

    /** A tiny valid PNG to upload. */
    private function png(string $name): UploadedFile
    {
        $path = sys_get_temp_dir().'/'.$name.'-'.uniqid().'.png';
        // 1×1 px PNG
        file_put_contents($path, (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkqP9fDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true));

        return new UploadedFile($path, $name.'.png', 'image/png', null, true);
    }

    private function em(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    private function home(): Page
    {
        $this->em()->clear();

        return $this->em()->getRepository(Page::class)->findOneBy(['isHome' => true]) ?? throw new \RuntimeException('no home');
    }

    /** @return list<PageBlock> */
    private function sortedBlocks(): array
    {
        $this->em()->clear();

        return $this->em()->getRepository(PageBlock::class)->findBy(['page' => $this->home()], ['position' => 'ASC', 'id' => 'ASC']);
    }
}
