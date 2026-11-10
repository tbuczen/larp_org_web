<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\LarpStatus;
use App\Enum\ProjectType;
use App\Repository\LarpRepository;
use App\Social\SocialLink;
use App\Social\SocialNetwork;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * A LARP project (or another initiative, e.g. a LARP group). Dates, places,
 * recruitment and characters belong to its editions (LarpEdition).
 */
#[ORM\Entity(repositoryClass: LarpRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['slug'])]
class Larp implements SluggableInterface, \Stringable
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 160)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 160)]
    private string $name = '';

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\Regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', message: 'slug.invalid')]
    private ?string $slug = null;

    #[ORM\Column(length: 20, enumType: ProjectType::class)]
    private ProjectType $projectType = ProjectType::Larp;

    #[ORM\Column(length: 20, enumType: LarpStatus::class)]
    private LarpStatus $status = LarpStatus::Draft;

    /** E.g. "Chamber larp", "Larp terenowy". */
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $format = null;

    /** Inspiration / setting, e.g. "Shadowrun", "Skyrim". */
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $setting = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Length(max: 500)]
    private ?string $teaser = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /** File name inside public/uploads/larps. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $coverImage = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $coverImageAlt = null;

    /** Free text, e.g. "30–40 graczy". */
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $playerCount = null;

    /** Free text, e.g. "od 250 zł". */
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $price = null;

    /**
     * The LARP's own social links as rows {network, url, label}; see SocialNetwork.
     * Stored as JSON so more networks need no schema change.
     *
     * @var list<array{network: string, url: string, label?: ?string}>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $socialLinks = null;

    #[ORM\Column]
    private bool $featured = false;

    #[ORM\Column]
    private int $position = 0;

    /** @var Collection<int, LarpEdition> */
    #[ORM\OneToMany(targetEntity: LarpEdition::class, mappedBy: 'larp', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['startDate' => 'ASC', 'id' => 'ASC'])]
    #[Assert\Valid]
    private Collection $editions;

    public function __construct()
    {
        $this->editions = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->name;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    #[Assert\Callback]
    public function validateSocialLinks(ExecutionContextInterface $context): void
    {
        foreach ($this->getSocialLinks() as $i => $row) {
            $network = SocialNetwork::tryFrom($row['network']);
            if (null === $network) {
                $context->buildViolation('social.network_unknown')->atPath('socialLinks['.$i.'][network]')->addViolation();
            } elseif (!$network->accepts($row['url'])) {
                $context->buildViolation([] === $network->hosts() ? 'social.url_https' : 'social.url_wrong_site')
                    ->setParameter('%network%', $network->label())
                    ->atPath('socialLinks['.$i.'][url]')
                    ->addViolation();
            }
        }
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
        return $this->name;
    }

    public function getProjectType(): ProjectType
    {
        return $this->projectType;
    }

    public function setProjectType(ProjectType $projectType): static
    {
        $this->projectType = $projectType;

        return $this;
    }

    public function isInitiative(): bool
    {
        return ProjectType::Initiative === $this->projectType;
    }

    public function getStatus(): LarpStatus
    {
        return $this->status;
    }

    public function setStatus(LarpStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function isPublished(): bool
    {
        return LarpStatus::Draft !== $this->status;
    }

    public function getFormat(): ?string
    {
        return $this->format;
    }

    public function setFormat(?string $format): static
    {
        $this->format = $format;

        return $this;
    }

    public function getSetting(): ?string
    {
        return $this->setting;
    }

    public function setSetting(?string $setting): static
    {
        $this->setting = $setting;

        return $this;
    }

    public function getTeaser(): ?string
    {
        return $this->teaser;
    }

    public function setTeaser(?string $teaser): static
    {
        $this->teaser = $teaser;

        return $this;
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

    public function getCoverImage(): ?string
    {
        return $this->coverImage;
    }

    public function setCoverImage(?string $coverImage): static
    {
        $this->coverImage = $coverImage;

        return $this;
    }

    public function getCoverImageAlt(): ?string
    {
        return $this->coverImageAlt;
    }

    public function setCoverImageAlt(?string $coverImageAlt): static
    {
        $this->coverImageAlt = $coverImageAlt;

        return $this;
    }

    public function getPlayerCount(): ?string
    {
        return $this->playerCount;
    }

    public function setPlayerCount(?string $playerCount): static
    {
        $this->playerCount = $playerCount;

        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(?string $price): static
    {
        $this->price = $price;

        return $this;
    }

    /**
     * Raw rows, as edited in the admin.
     *
     * @return list<array{network: string, url: string, label?: ?string}>
     */
    public function getSocialLinks(): array
    {
        return $this->socialLinks ?? [];
    }

    /**
     * Rows without an address are dropped; addresses and labels are trimmed.
     *
     * @param iterable<mixed> $rows
     */
    public function setSocialLinks(iterable $rows): static
    {
        $clean = [];
        foreach ($rows as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $url = trim((string) ($row['url'] ?? ''));
            if ('' === $url) {
                continue;
            }
            $label = trim((string) ($row['label'] ?? ''));
            $clean[] = ['network' => (string) ($row['network'] ?? ''), 'url' => $url, 'label' => '' === $label ? null : $label];
        }
        $this->socialLinks = [] === $clean ? null : $clean;

        return $this;
    }

    /**
     * This LARP's social buttons.
     *
     * @return list<SocialLink>
     */
    public function getSocialButtons(): array
    {
        return SocialLink::fromRows($this->getSocialLinks());
    }

    public function isFeatured(): bool
    {
        return $this->featured;
    }

    public function setFeatured(bool $featured): static
    {
        $this->featured = $featured;

        return $this;
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

    /** @return Collection<int, LarpEdition> */
    public function getEditions(): Collection
    {
        return $this->editions;
    }

    public function getEditionCount(): int
    {
        return $this->editions->count();
    }

    public function addEdition(LarpEdition $edition): static
    {
        if (!$this->editions->contains($edition)) {
            $this->editions->add($edition);
            $edition->setLarp($this);
        }

        return $this;
    }

    public function removeEdition(LarpEdition $edition): static
    {
        $this->editions->removeElement($edition);

        return $this;
    }

    /**
     * The edition shown on the LARP page: the next upcoming one, otherwise the latest.
     */
    public function getCurrentEdition(?\DateTimeImmutable $today = null): ?LarpEdition
    {
        $today ??= new \DateTimeImmutable('today');
        $editions = $this->getSortedEditions();
        foreach ($editions as $edition) {
            if (null !== $edition->getEndOrStartDate() && $edition->getEndOrStartDate() >= $today) {
                return $edition;
            }
        }

        return [] === $editions ? null : end($editions);
    }

    /**
     * Year span of all editions, e.g. "2023–2025" or "2023". Null when no edition has a date.
     */
    public function getYearRange(): ?string
    {
        $years = [];
        foreach ($this->editions as $edition) {
            if (null !== $edition->getStartDate()) {
                $years[] = (int) $edition->getStartDate()->format('Y');
            }
        }
        if ([] === $years) {
            return null;
        }
        $first = min($years);
        $last = max($years);

        return $first === $last ? (string) $first : $first.'–'.$last;
    }

    /** @return list<LarpEdition> sorted by start date, undated editions last */
    public function getSortedEditions(): array
    {
        $editions = $this->editions->toArray();
        usort($editions, static fn (LarpEdition $a, LarpEdition $b): int => [null === $a->getStartDate(), $a->getStartDate()] <=> [null === $b->getStartDate(), $b->getStartDate()]);

        return $editions;
    }
}
