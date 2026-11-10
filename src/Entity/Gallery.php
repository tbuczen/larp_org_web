<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\GalleryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * A photo gallery, usually from one LARP (and one of its editions). Galleries
 * without a LARP are possible too, e.g. for an initiative or a workshop.
 *
 * Most galleries are an album on another site (Google Photos, Facebook, ...):
 * externalUrl plus a cover image uploaded here. Photos can also be uploaded
 * here — then the site shows them (all, or a selection with a link to the full
 * album). Cover: the uploaded cover image, else the first photo.
 */
#[ORM\Entity(repositoryClass: GalleryRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['slug'])]
class Gallery implements SluggableInterface, \Stringable
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 160)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 160)]
    private string $title = '';

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\Regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', message: 'slug.invalid')]
    private ?string $slug = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Larp $larp = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?LarpEdition $edition = null;

    /** The album on another site (Google Photos, Facebook, ...). */
    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Url(protocols: ['https'], requireTld: true, message: 'gallery.url_https')]
    #[Assert\Length(max: 500)]
    private ?string $externalUrl = null;

    /** File name inside public/uploads/galleries/covers/. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $coverImage = null;

    /** Shown as "Zdjęcia: …"; single photos can name someone else. */
    #[ORM\Column(length: 160, nullable: true)]
    #[Assert\Length(max: 160)]
    private ?string $photographer = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $eventDate = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private bool $published = false;

    /** @var Collection<int, Photo> */
    // EXTRA_LAZY: lists count photos and fetch the cover without loading them all.
    #[ORM\OneToMany(targetEntity: Photo::class, mappedBy: 'gallery', cascade: ['persist', 'remove'], orphanRemoval: true, fetch: 'EXTRA_LAZY')]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $photos;

    public function __construct()
    {
        $this->photos = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->title;
    }

    #[Assert\Callback]
    public function validateEdition(ExecutionContextInterface $context): void
    {
        if (null !== $this->edition && null !== $this->larp && $this->edition->getLarp() !== $this->larp) {
            $context->buildViolation('gallery.edition_of_other_larp')->atPath('edition')->addViolation();
        }
    }

    /** Picking an edition is enough: its LARP is filled in. */
    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function syncLarpWithEdition(): void
    {
        if (null !== $this->edition && null === $this->larp) {
            $this->larp = $this->edition->getLarp();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getSlugSource(): string
    {
        return $this->title;
    }

    public function getLarp(): ?Larp
    {
        return $this->larp;
    }

    public function setLarp(?Larp $larp): static
    {
        $this->larp = $larp;

        return $this;
    }

    public function getEdition(): ?LarpEdition
    {
        return $this->edition;
    }

    public function setEdition(?LarpEdition $edition): static
    {
        $this->edition = $edition;
        if (null !== $edition && null === $this->larp) {
            $this->larp = $edition->getLarp();
        }

        return $this;
    }

    public function getExternalUrl(): ?string
    {
        return $this->externalUrl;
    }

    public function setExternalUrl(?string $externalUrl): static
    {
        $this->externalUrl = null === $externalUrl || '' === trim($externalUrl) ? null : trim($externalUrl);

        return $this;
    }

    public function isExternal(): bool
    {
        return null !== $this->externalUrl;
    }

    /** Only a link: visitors go straight to the album on the other site. */
    public function isLinkOnly(): bool
    {
        return $this->isExternal() && 0 === $this->getPhotoCount();
    }

    /** Where the album is: "google", "facebook", or "other" (null = not external). */
    public function getSource(): ?string
    {
        $host = $this->getExternalHost();
        if (null === $host) {
            return null;
        }

        return match (true) {
            \in_array($host, ['photos.google.com', 'photos.app.goo.gl', 'goo.gl'], true) => 'google',
            \in_array($host, ['facebook.com', 'm.facebook.com', 'fb.com', 'fb.me', 'fb.watch'], true) => 'facebook',
            default => 'other',
        };
    }

    /** The album site's host without "www.", e.g. "flickr.com". */
    public function getExternalHost(): ?string
    {
        $host = null === $this->externalUrl ? null : parse_url($this->externalUrl, \PHP_URL_HOST);

        return \is_string($host) ? preg_replace('/^www\./', '', strtolower($host)) : null;
    }

    public function getCoverImage(): ?string
    {
        return $this->coverImage;
    }

    public function setCoverImage(?string $coverImage): static
    {
        $this->coverImage = $coverImage;

        return $this;
    }

    public function getPhotographer(): ?string
    {
        return $this->photographer;
    }

    public function setPhotographer(?string $photographer): static
    {
        $this->photographer = $photographer;

        return $this;
    }

    public function getEventDate(): ?\DateTimeImmutable
    {
        return $this->eventDate;
    }

    public function setEventDate(?\DateTimeImmutable $eventDate): static
    {
        $this->eventDate = $eventDate;

        return $this;
    }

    /** The event date, or else the edition's start date. */
    public function getDate(): ?\DateTimeImmutable
    {
        return $this->eventDate ?? $this->edition?->getStartDate();
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function isPublished(): bool
    {
        return $this->published;
    }

    public function setPublished(bool $published): static
    {
        $this->published = $published;

        return $this;
    }

    /** Visible on the site: published, and its LARP (if any) isn't a draft. */
    public function isVisible(): bool
    {
        return $this->published && (null === $this->larp || $this->larp->isPublished());
    }

    /** @return Collection<int, Photo> */
    public function getPhotos(): Collection
    {
        return $this->photos;
    }

    public function addPhoto(Photo $photo): static
    {
        if (!$this->photos->contains($photo)) {
            $photo->setGallery($this);
            $photo->setPosition(\count($this->photos));
            $this->photos->add($photo);
        }

        return $this;
    }

    public function removePhoto(Photo $photo): static
    {
        $this->photos->removeElement($photo);

        return $this;
    }

    public function getPhotoCount(): int
    {
        return \count($this->photos);
    }

    public function getCover(): ?Photo
    {
        return $this->photos->slice(0, 1)[0] ?? null;
    }

    /** Cover picture, as a path inside public/uploads/galleries/ (null = none). */
    public function getCoverPath(): ?string
    {
        return null !== $this->coverImage ? 'covers/'.$this->coverImage : $this->getCover()?->getThumbnailOrFile();
    }

    /** Credit line for the whole gallery: its photographer, or the photos' own credits. */
    public function getCredits(): ?string
    {
        if (null !== $this->photographer && '' !== $this->photographer) {
            return $this->photographer;
        }
        $names = array_unique(array_filter($this->photos->map(static fn (Photo $p): ?string => $p->getPhotographer())->toArray()));

        return [] === $names ? null : implode(', ', $names);
    }
}
