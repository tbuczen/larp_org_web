<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PhotoRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * One photo of a gallery. Files live in public/uploads/galleries/: the photo
 * itself (at most 2560 px on its long side) and a small thumbnail for grids.
 * They're deleted with the photo (App\Gallery\PhotoFileListener).
 */
#[ORM\Entity(repositoryClass: PhotoRepository::class)]
class Photo implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'photos')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Gallery $gallery = null;

    /** Path inside public/uploads/galleries/. */
    #[ORM\Column(length: 255)]
    private string $file = '';

    /** Path of the thumbnail inside public/uploads/galleries/; null = use the photo. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $thumbnail = null;

    #[ORM\Column]
    private int $width = 0;

    #[ORM\Column]
    private int $height = 0;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Length(max: 500)]
    private ?string $caption = null;

    /** Overrides the gallery's photographer for this photo. */
    #[ORM\Column(length: 160, nullable: true)]
    #[Assert\Length(max: 160)]
    private ?string $photographer = null;

    #[ORM\Column]
    private int $position = 0;

    public function __toString(): string
    {
        return $this->caption ?? $this->file;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGallery(): ?Gallery
    {
        return $this->gallery;
    }

    public function setGallery(?Gallery $gallery): static
    {
        $this->gallery = $gallery;

        return $this;
    }

    public function getFile(): string
    {
        return $this->file;
    }

    public function setFile(string $file): static
    {
        $this->file = $file;

        return $this;
    }

    public function getThumbnail(): ?string
    {
        return $this->thumbnail;
    }

    public function setThumbnail(?string $thumbnail): static
    {
        $this->thumbnail = $thumbnail;

        return $this;
    }

    /** The thumbnail, or the photo itself when there is none. */
    public function getThumbnailOrFile(): string
    {
        return $this->thumbnail ?? $this->file;
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function setSize(int $width, int $height): static
    {
        $this->width = $width;
        $this->height = $height;

        return $this;
    }

    public function getCaption(): ?string
    {
        return $this->caption;
    }

    public function setCaption(?string $caption): static
    {
        $this->caption = null === $caption || '' === trim($caption) ? null : trim($caption);

        return $this;
    }

    public function getPhotographer(): ?string
    {
        return $this->photographer;
    }

    public function setPhotographer(?string $photographer): static
    {
        $this->photographer = null === $photographer || '' === trim($photographer) ? null : trim($photographer);

        return $this;
    }

    /** Who took it: this photo's credit, or the gallery's. */
    public function getCredit(): ?string
    {
        return $this->photographer ?? $this->gallery?->getPhotographer();
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }
}
