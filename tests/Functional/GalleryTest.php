<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\DataFixtures\AppFixtures;
use App\Entity\Gallery;
use App\Entity\Larp;
use App\Entity\Photo;
use App\Enum\LarpStatus;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class GalleryTest extends WebTestCase
{
    use RollbackTransactionTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->startTransaction($this->client);
    }

    public function testEditorCreatesAGalleryUploadsReordersCaptionsAndDeletesPhotos(): void
    {
        $this->login();
        $edition = $this->larp('pradawne-zwoje')->getCurrentEdition();
        self::assertNotNull($edition);

        $crawler = $this->client->request('GET', '/admin/gallery/new');
        $form = $crawler->filter('form[name="Gallery"]')->form();
        $form['Gallery[title]'] = 'Pradawne Zwoje — dzień pierwszy';
        $form['Gallery[slug]'] = 'pradawne-zwoje-dzien-pierwszy';
        $form['Gallery[edition]'] = (string) $edition->getId();
        $form['Gallery[photographer]'] = 'Anna Kowalska';
        $form['Gallery[published]']->tick();
        $this->client->submit($form);

        // Straight to the photos screen; picking the edition filled in its LARP.
        $gallery = $this->em()->getRepository(Gallery::class)->findOneBy(['slug' => 'pradawne-zwoje-dzien-pierwszy']);
        self::assertNotNull($gallery);
        self::assertSame('pradawne-zwoje', $gallery->getLarp()?->getSlug());
        self::assertResponseRedirects();
        $crawler = $this->client->followRedirect();
        self::assertSelectorExists('[data-photo-upload-url][data-csrf-token]');
        $token = (string) $crawler->filter('[data-photo-upload-url]')->attr('data-csrf-token');
        $uploadUrl = (string) $crawler->filter('[data-photo-upload-url]')->attr('data-photo-upload-url');

        // The page sends photos one per request.
        foreach (['oboz', 'bitwa', 'narada'] as $name) {
            $this->client->request('POST', $uploadUrl, [], ['photo' => $this->image($name, 3000, 2000)], ['HTTP_X_CSRF_TOKEN' => $token]);
            self::assertResponseStatusCodeSame(201);
        }

        $photos = $this->photos($gallery->getId());
        self::assertCount(3, $photos);
        $first = $photos[0];
        self::assertStringStartsWith($gallery->getId().'/oboz-', $first->getFile());
        self::assertFileExists($this->dir().'/'.$first->getFile());
        if (\function_exists('imagecreatetruecolor')) {
            self::assertSame([2560, 1707], [$first->getWidth(), $first->getHeight()], 'shrunk to 2560 px');
            self::assertNotNull($first->getThumbnail());
            self::assertFileExists($this->dir().'/'.$first->getThumbnail());
            self::assertLessThanOrEqual(720, (int) getimagesize($this->dir().'/'.$first->getThumbnail())[0]);
        }

        // Save: new order (narada, oboz), captions and credits, delete "bitwa".
        [$oboz, $bitwa, $narada] = $photos;
        $crawler = $this->client->request('GET', '/admin/gallery/'.$gallery->getId().'/photos');
        self::assertCount(3, $crawler->filter('.photo-manager__item'));
        $this->client->request('POST', '/admin/gallery/'.$gallery->getId().'/photos/save', [
            '_token' => $crawler->filter('form[action$="/photos/save"] input[name="_token"]')->attr('value'),
            'photos' => [
                $narada->getId() => ['caption' => 'Narada jarlów', 'photographer' => 'Jan Nowak'],
                $oboz->getId() => ['caption' => '  ', 'photographer' => ''],
                $bitwa->getId() => ['caption' => 'x', 'photographer' => '', 'delete' => '1'],
            ],
        ]);
        self::assertResponseRedirects();

        $photos = $this->photos($gallery->getId());
        self::assertSame([$narada->getId(), $oboz->getId()], array_map(static fn (Photo $p): ?int => $p->getId(), $photos));
        self::assertSame('Narada jarlów', $photos[0]->getCaption());
        self::assertSame('Jan Nowak', $photos[0]->getCredit());
        self::assertNull($photos[1]->getCaption());
        self::assertSame('Anna Kowalska', $photos[1]->getCredit(), "falls back to the gallery's photographer");
        self::assertFileDoesNotExist($this->dir().'/'.$bitwa->getFile(), 'files go with the photo');

        // Public pages.
        $crawler = $this->client->request('GET', '/galeria/pradawne-zwoje-dzien-pierwszy');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Pradawne Zwoje — dzień pierwszy');
        self::assertSelectorTextContains('.gallery-page__meta', '2 zdjęcia');
        self::assertSelectorTextContains('.gallery-page__meta', 'Zdjęcia: Anna Kowalska');
        self::assertCount(2, $crawler->filter('.photo-grid__item'));
        self::assertSame('Narada jarlów', $crawler->filter('.photo-grid__item img')->first()->attr('alt'));
        /** @var list<array{src: string, caption: string}> $viewer */
        $viewer = json_decode((string) $crawler->filter('[data-controller="lightbox"]')->attr('data-lightbox-photos-value'), true);
        self::assertSame('Narada jarlów — fot. Jan Nowak', $viewer[0]['caption']);

        $crawler = $this->client->request('GET', '/larp/pradawne-zwoje');
        self::assertSelectorTextContains('.larp-galleries', 'Pradawne Zwoje — dzień pierwszy');
        $this->client->request('GET', '/galerie');
        self::assertSelectorTextContains('.gallery-cards', 'Pradawne Zwoje — dzień pierwszy');

        // Deleting the gallery removes its photos' files and folder.
        $id = $gallery->getId();
        $this->em()->remove($this->em()->find(Gallery::class, $id) ?? throw new \LogicException());
        $this->em()->flush();
        self::assertDirectoryDoesNotExist($this->dir().'/'.$id);
    }

    public function testAGalleryIsUsuallyALinkToAnAlbumElsewhereWithACover(): void
    {
        $this->login();
        $crawler = $this->client->request('GET', '/admin/gallery/new');
        $form = $crawler->filter('form[name="Gallery"]')->form();
        $form['Gallery[title]'] = 'Vault 47 — zdjęcia z gry';
        $form['Gallery[slug]'] = 'vault-47-zdjecia';
        $form['Gallery[larp]'] = (string) $this->larp('vault-47')->getId();
        $form['Gallery[externalUrl]'] = 'http://photos.app.goo.gl/AbC123';
        $form['Gallery[published]']->tick();
        $crawler = $this->client->submit($form);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('https://', $crawler->filter('.invalid-feedback')->text());

        $form = $crawler->filter('form[name="Gallery"]')->form();
        $form['Gallery[externalUrl]'] = 'https://photos.app.goo.gl/AbC123';
        $form['Gallery[coverImage][file]']->upload($this->image('okladka', 1200, 800)->getPathname());
        $this->client->submit($form);
        self::assertResponseRedirects('', null, 'link galleries go back to the list, not to the photos screen');
        self::assertStringNotContainsString('/photos', (string) $this->client->getResponse()->headers->get('Location'));

        $gallery = $this->em()->getRepository(Gallery::class)->findOneBy(['slug' => 'vault-47-zdjecia']);
        self::assertNotNull($gallery);
        self::assertTrue($gallery->isLinkOnly());
        self::assertSame('google', $gallery->getSource());
        $cover = (string) $gallery->getCoverImage();
        self::assertFileExists($this->dir().'/covers/'.$cover);

        // The card opens the album in a new tab and says where it is.
        $crawler = $this->client->request('GET', '/larp/vault-47');
        $card = $crawler->filter('.larp-galleries .gallery-card');
        self::assertCount(1, $card);
        self::assertSame('https://photos.app.goo.gl/AbC123', $card->filter('.gallery-card__title a')->attr('href'));
        self::assertSame('_blank', $card->filter('.gallery-card__title a')->attr('target'));
        self::assertStringContainsString('Google Photos', $card->filter('.gallery-card__count')->text());
        self::assertStringContainsString('covers/'.$cover, (string) $card->filter('img')->attr('src'));

        // Its own address just forwards to the album.
        $this->client->request('GET', '/galeria/vault-47-zdjecia');
        self::assertResponseRedirects('https://photos.app.goo.gl/AbC123');

        // With a few photos uploaded here, the site shows them plus a button to the whole album.
        $photo = (new Photo())->setFile($gallery->getId().'/a.jpg')->setSize(1200, 800);
        $gallery = $this->em()->find(Gallery::class, $gallery->getId()) ?? throw new \LogicException();
        $gallery->setExternalUrl('https://www.facebook.com/media/set/?set=a.123')->addPhoto($photo);
        $this->em()->flush();
        $crawler = $this->client->request('GET', '/galeria/vault-47-zdjecia');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.gallery-page__head a.button', 'Cały album: Facebook');
        self::assertCount(1, $crawler->filter('.photo-grid__item'));
        $crawler = $this->client->request('GET', '/galerie');
        self::assertSame('/galeria/vault-47-zdjecia', $crawler->filter('.gallery-card__title a')->attr('href'));

        // The cover goes with the gallery.
        $this->em()->remove($this->em()->find(Gallery::class, $gallery->getId()) ?? throw new \LogicException());
        $this->em()->flush();
        self::assertFileDoesNotExist($this->dir().'/covers/'.$cover);
    }

    public function testUploadRejectsBadTokensAndNonImages(): void
    {
        $this->login();
        $gallery = $this->gallery('Testowa', published: true);
        $crawler = $this->client->request('GET', '/admin/gallery/'.$gallery->getId().'/photos');
        $token = (string) $crawler->filter('[data-photo-upload-url]')->attr('data-csrf-token');
        $url = '/admin/gallery/'.$gallery->getId().'/photos/upload';

        $this->client->request('POST', $url, [], ['photo' => $this->image('a', 10, 10)], ['HTTP_X_CSRF_TOKEN' => 'nope']);
        self::assertResponseStatusCodeSame(403);

        $text = sys_get_temp_dir().'/not-an-image-'.uniqid().'.jpg';
        file_put_contents($text, 'hello');
        $this->client->request('POST', $url, [], ['photo' => new UploadedFile($text, 'fake.jpg', 'image/jpeg', null, true)], ['HTTP_X_CSRF_TOKEN' => $token]);
        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('error', (array) json_decode((string) $this->client->getResponse()->getContent(), true));

        $this->client->request('POST', $url, [], [], ['HTTP_X_CSRF_TOKEN' => $token]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->em()->getRepository(Photo::class)->count(['gallery' => $gallery]));
    }

    public function testOnlyPublishedGalleriesOfPublishedLarpsAreVisible(): void
    {
        $this->gallery('Szkic', published: false);
        $draftLarp = $this->larp('vault-47')->setStatus(LarpStatus::Draft);
        $this->gallery('Z wersji roboczej', published: true, larp: $draftLarp);
        $this->gallery('Warsztaty', published: true);

        $crawler = $this->client->request('GET', '/galerie');
        self::assertResponseIsSuccessful();
        self::assertSame(['Warsztaty'], $crawler->filter('.gallery-card__title')->each(static fn (Crawler $c): string => trim($c->text())));
        self::assertSelectorTextContains('.gallery-card', '0', 'shows the photo count');

        $this->client->request('GET', '/galeria/szkic');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/galeria/z-wersji-roboczej');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/galeria/warsztaty');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.gallery-page', 'W tej galerii nie ma jeszcze zdjęć.');
    }

    public function testGalleriesCanBeLinkedFromTheMenuAndArentPageSlugs(): void
    {
        $this->login();
        $crawler = $this->client->request('GET', '/admin/menu-item/new');
        self::assertStringContainsString('Galerie zdjęć', $crawler->filter('body')->text());

        $this->client->request('GET', '/galerie');
        self::assertSelectorTextContains('h1', 'Galerie zdjęć');
    }

    private function login(): void
    {
        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => AppFixtures::EDITOR_EMAIL]);
        self::assertNotNull($user);
        $this->client->loginUser($user);
    }

    private function gallery(string $title, bool $published, ?Larp $larp = null): Gallery
    {
        $gallery = (new Gallery())->setTitle($title)->setPublished($published)->setLarp($larp);
        $this->em()->persist($gallery);
        $this->em()->flush();

        return $gallery;
    }

    private function larp(string $slug): Larp
    {
        $larp = $this->em()->getRepository(Larp::class)->findOneBy(['slug' => $slug]);
        self::assertInstanceOf(Larp::class, $larp);

        return $larp;
    }

    /** @return list<Photo> */
    private function photos(?int $galleryId): array
    {
        $this->em()->clear();

        return $this->em()->getRepository(Photo::class)->findBy(['gallery' => $galleryId], ['position' => 'ASC', 'id' => 'ASC']);
    }

    /** A JPEG of the given size (a 1×1 PNG when GD is missing). */
    private function image(string $name, int $width, int $height): UploadedFile
    {
        $path = sys_get_temp_dir().'/'.$name.'-'.uniqid().'.jpg';
        if (\function_exists('imagecreatetruecolor')) {
            $image = imagecreatetruecolor($width, $height);
            imagefill($image, 0, 0, (int) imagecolorallocate($image, 90, 110, 70));
            imagejpeg($image, $path, 60);
        } else {
            $path = substr($path, 0, -4).'.png';
            file_put_contents($path, (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkqP9fDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true));
        }

        return new UploadedFile($path, basename($path), null, null, true);
    }

    private function dir(): string
    {
        return (string) self::getContainer()->getParameter('app.gallery_dir');
    }

    private function em(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
