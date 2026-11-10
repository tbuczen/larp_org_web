<?php

declare(strict_types=1);

namespace App\Gallery;

use App\Entity\Gallery;
use App\Entity\Photo;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Saves uploaded gallery photos under public/uploads/galleries/<gallery id>/:
 * the photo (long side capped at MAX_SIZE px) plus thumbs/<name>.webp for grids.
 */
class PhotoStorage
{
    public const BASE_PATH = 'uploads/galleries';
    public const MAX_SIZE = 2560;
    public const THUMB_SIZE = 720;

    public function __construct(
        private readonly ImageResizer $resizer,
        private readonly SluggerInterface $slugger,
        #[Autowire('%app.gallery_dir%')]
        private readonly string $dir,
    ) {
    }

    /** Moves the upload into place and returns a new photo (not persisted yet). */
    public function store(UploadedFile $upload, Gallery $gallery): Photo
    {
        $id = $gallery->getId() ?? throw new \LogicException('Save the gallery before adding photos.');
        $base = $this->slugger->slug(pathinfo($upload->getClientOriginalName(), \PATHINFO_FILENAME))->lower()->truncate(60)->toString();
        $extension = $upload->guessExtension() ?? 'jpg';
        $name = sprintf('%s-%s.%s', '' !== $base ? $base : 'photo', bin2hex(random_bytes(4)), 'jpeg' === $extension ? 'jpg' : $extension);

        $upload->move($this->dir.'/'.$id, $name);
        $path = $this->dir.'/'.$id.'/'.$name;

        $this->resizer->limit($path, self::MAX_SIZE);
        $thumbnail = $this->resizer->thumbnail($path, $this->dir.'/'.$id.'/thumbs/'.$name, self::THUMB_SIZE);
        $size = @getimagesize($path) ?: [0, 0];

        return (new Photo())
            ->setFile($id.'/'.$name)
            ->setThumbnail(null === $thumbnail ? null : $id.'/thumbs/'.basename($thumbnail))
            ->setSize((int) $size[0], (int) $size[1]);
    }

    public function delete(Photo $photo): void
    {
        foreach ([$photo->getFile(), $photo->getThumbnail()] as $file) {
            if (null !== $file && '' !== $file && !str_contains($file, '..') && is_file($this->dir.'/'.$file)) {
                unlink($this->dir.'/'.$file);
            }
        }
    }

    public function deleteCover(string $coverImage): void
    {
        $path = $this->dir.'/covers/'.basename($coverImage);
        if (is_file($path)) {
            unlink($path);
        }
    }

    /** Removes the gallery's folder once its photos are gone. */
    public function deleteFolder(int $galleryId): void
    {
        foreach ([$this->dir.'/'.$galleryId.'/thumbs', $this->dir.'/'.$galleryId] as $folder) {
            if (is_dir($folder) && [] === array_diff(scandir($folder) ?: [], ['.', '..'])) {
                rmdir($folder);
            }
        }
    }

    public function path(string $file): string
    {
        return $this->dir.'/'.$file;
    }
}
