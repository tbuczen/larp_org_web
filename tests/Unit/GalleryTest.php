<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Gallery;
use App\Entity\Larp;
use App\Entity\LarpEdition;
use App\Entity\Photo;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Validation;

final class GalleryTest extends TestCase
{
    public function testPickingAnEditionFillsInItsLarp(): void
    {
        $larp = (new Larp())->setName('Vault 47');
        $edition = (new LarpEdition())->setName('2025');
        $larp->addEdition($edition);

        $gallery = (new Gallery())->setEdition($edition);

        self::assertSame($larp, $gallery->getLarp());
    }

    public function testEditionMustBelongToTheChosenLarp(): void
    {
        $other = (new Larp())->setName('Cybercrush');
        $edition = (new LarpEdition())->setName('2025');
        (new Larp())->setName('Vault 47')->addEdition($edition);
        $gallery = (new Gallery())->setTitle('Zdjęcia')->setLarp($other)->setEdition($edition);

        $violations = Validation::createValidator()->validate($gallery, new Callback(
            static fn (Gallery $g, ExecutionContextInterface $context) => $g->validateEdition($context),
        ));

        self::assertCount(1, $violations);
        self::assertSame('edition', $violations->get(0)->getPropertyPath());
    }

    public function testAlbumSourceIsRecognisedFromTheLink(): void
    {
        $gallery = new Gallery();
        self::assertNull($gallery->getSource());
        self::assertFalse($gallery->isExternal());

        foreach ([
            'https://photos.app.goo.gl/AbC123' => 'google',
            'https://photos.google.com/share/xyz' => 'google',
            'https://www.facebook.com/media/set/?set=a.1' => 'facebook',
            'https://fb.me/abc' => 'facebook',
            'https://www.flickr.com/photos/x/albums/1' => 'other',
        ] as $url => $source) {
            self::assertSame($source, $gallery->setExternalUrl($url)->getSource(), $url);
        }
        self::assertSame('flickr.com', $gallery->getExternalHost());
        self::assertTrue($gallery->isLinkOnly());

        $gallery->addPhoto((new Photo())->setFile('1/a.jpg'));
        self::assertFalse($gallery->isLinkOnly(), 'with photos it has its own page');
        self::assertSame('1/a.jpg', $gallery->getCoverPath());
        self::assertSame('covers/c.jpg', $gallery->setCoverImage('c.jpg')->getCoverPath(), 'an uploaded cover wins');
        self::assertNull($gallery->setExternalUrl('  ')->getExternalUrl());
    }

    public function testCoverCountAndCredits(): void
    {
        $gallery = (new Gallery())->setTitle('Zdjęcia');
        self::assertNull($gallery->getCover());
        self::assertNull($gallery->getCredits());

        $gallery->addPhoto((new Photo())->setFile('1/a.jpg')->setPhotographer('Jan'));
        $gallery->addPhoto((new Photo())->setFile('1/b.jpg')->setPhotographer('Ola'));
        $gallery->addPhoto((new Photo())->setFile('1/c.jpg')->setPhotographer('Jan'));

        self::assertSame('1/a.jpg', $gallery->getCover()?->getFile());
        self::assertSame(3, $gallery->getPhotoCount());
        self::assertSame([0, 1, 2], $gallery->getPhotos()->map(static fn (Photo $p): int => $p->getPosition())->getValues());
        self::assertSame('Jan, Ola', $gallery->getCredits(), "without a gallery photographer: the photos' credits");
        self::assertSame('Anna', $gallery->setPhotographer('Anna')->getCredits());
    }
}
