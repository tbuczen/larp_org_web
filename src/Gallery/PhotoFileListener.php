<?php

declare(strict_types=1);

namespace App\Gallery;

use App\Entity\Gallery;
use App\Entity\Photo;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

/**
 * Deletes a photo's files when it's removed (also when its whole gallery is),
 * and the gallery's cover image and empty folder afterwards.
 */
#[AsEntityListener(event: Events::postRemove, method: 'photoRemoved', entity: Photo::class)]
#[AsEntityListener(event: Events::preRemove, method: 'galleryRemoving', entity: Gallery::class)]
#[AsEntityListener(event: Events::postRemove, method: 'galleryRemoved', entity: Gallery::class)]
final class PhotoFileListener
{
    /** @var list<int> */
    private array $removedGalleries = [];

    /** @var list<string> */
    private array $removedCovers = [];

    public function __construct(private readonly PhotoStorage $storage)
    {
    }

    public function photoRemoved(Photo $photo): void
    {
        $this->storage->delete($photo);
    }

    public function galleryRemoving(Gallery $gallery): void
    {
        if (null !== $gallery->getId()) {
            $this->removedGalleries[] = $gallery->getId();
        }
        if (null !== $gallery->getCoverImage()) {
            $this->removedCovers[] = $gallery->getCoverImage();
        }
    }

    public function galleryRemoved(): void
    {
        foreach ($this->removedGalleries as $id) {
            $this->storage->deleteFolder($id);
        }
        foreach ($this->removedCovers as $cover) {
            $this->storage->deleteCover($cover);
        }
        $this->removedGalleries = $this->removedCovers = [];
    }
}
