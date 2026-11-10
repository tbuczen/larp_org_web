<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\CharacterAvailability;
use App\Enum\RecruitmentStatus;
use App\Repository\LarpEditionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * One run of a LARP: its dates, place, recruitment, characters and galleries.
 * Each edition has its own page at /larp/{larp}/{edition slug}; tags belong to
 * the LARP, so all its editions share them.
 */
#[ORM\Entity(repositoryClass: LarpEditionRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(fields: ['larp', 'slug'])]
class LarpEdition implements SluggableInterface, \Stringable
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'editions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Larp $larp = null;

    /** Words that are other pages under /larp/{larp}/ and can't be edition addresses. */
    public const RESERVED_SLUGS = ['postacie', 'postac', 'kalendarz-ics', 'galerie'];

    /** In the address: /larp/{larp}/{slug}. Filled from the name when empty (e.g. "3-edycja"). */
    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', message: 'slug.invalid')]
    #[Assert\Length(max: 120)]
    private ?string $slug = null;

    /** E.g. "3. edycja" or "2026". */
    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    private string $name = '';

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $startDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $endDate = null;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url(requireTld: true)]
    private ?string $locationUrl = null;

    #[ORM\Column(length: 20, enumType: RecruitmentStatus::class)]
    private RecruitmentStatus $recruitment = RecruitmentStatus::NotStarted;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url(requireTld: true)]
    private ?string $registrationUrl = null;

    /** The edition's Facebook event (facebook.com/events/… or fb.me/e/…). */
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Regex('#^https://((www|m|web)\.)?(facebook\.com/events/|fb\.me/e/)[^\s]+$#', message: 'edition.facebook_event_invalid')]
    private ?string $facebookEventUrl = null;

    /** @var Collection<int, Character> */
    #[ORM\OneToMany(targetEntity: Character::class, mappedBy: 'edition', cascade: ['remove'])]
    #[ORM\OrderBy(['position' => 'ASC', 'name' => 'ASC'])]
    private Collection $characters;

    public function __construct()
    {
        $this->characters = new ArrayCollection();
    }

    public function __toString(): string
    {
        return trim(($this->larp?->getName() ?? '').' — '.$this->name, ' —');
    }

    #[Assert\Callback]
    public function validateSlug(ExecutionContextInterface $context): void
    {
        if (\in_array($this->slug, self::RESERVED_SLUGS, true)) {
            $context->buildViolation('edition.reserved_slug')->atPath('slug')->addViolation();
        }
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): static
    {
        $this->slug = null === $slug || '' === trim($slug) ? null : trim($slug);

        return $this;
    }

    public function getSlugSource(): string
    {
        return $this->name;
    }

    #[Assert\Callback]
    public function validateDates(ExecutionContextInterface $context): void
    {
        if (null !== $this->startDate && null !== $this->endDate && $this->endDate < $this->startDate) {
            $context->buildViolation('edition.end_before_start')->atPath('endDate')->addViolation();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getStartDate(): ?\DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTimeImmutable $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeImmutable $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getEndOrStartDate(): ?\DateTimeImmutable
    {
        return $this->endDate ?? $this->startDate;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): static
    {
        $this->location = $location;

        return $this;
    }

    public function getLocationUrl(): ?string
    {
        return $this->locationUrl;
    }

    public function setLocationUrl(?string $locationUrl): static
    {
        $this->locationUrl = $locationUrl;

        return $this;
    }

    public function getRecruitment(): RecruitmentStatus
    {
        return $this->recruitment;
    }

    public function setRecruitment(RecruitmentStatus $recruitment): static
    {
        $this->recruitment = $recruitment;

        return $this;
    }

    public function getFacebookEventUrl(): ?string
    {
        return $this->facebookEventUrl;
    }

    public function setFacebookEventUrl(?string $facebookEventUrl): static
    {
        $this->facebookEventUrl = null === $facebookEventUrl || '' === trim($facebookEventUrl) ? null : trim($facebookEventUrl);

        return $this;
    }

    /** Whether it has dates and hasn't ended yet (so "add to calendar" makes sense). */
    public function isUpcoming(?\DateTimeImmutable $today = null): bool
    {
        $today ??= new \DateTimeImmutable('today');
        $last = $this->endDate ?? $this->startDate;

        return null !== $last && $last >= $today;
    }

    public function getRegistrationUrl(): ?string
    {
        return $this->registrationUrl;
    }

    public function setRegistrationUrl(?string $registrationUrl): static
    {
        $this->registrationUrl = $registrationUrl;

        return $this;
    }

    /** @return Collection<int, Character> */
    public function getCharacters(): Collection
    {
        return $this->characters;
    }

    /** @return list<Character> characters visitors may see (not hidden) */
    public function getVisibleCharacters(): array
    {
        return array_values($this->characters->filter(
            static fn (Character $c): bool => CharacterAvailability::Hidden !== $c->getAvailability(),
        )->toArray());
    }
}
