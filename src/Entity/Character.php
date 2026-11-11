<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\CharacterAvailability;
use App\Enum\CharacterCasting;
use App\Enum\TagType;
use App\Repository\CharacterRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * A character in one LARP edition, with trigger and gameplay tags (each with a %).
 *
 * All tags live in one collection ($characterTags). The admin form edits them as
 * two lists, triggerTags and gameplayTags, which are filtered views of it.
 */
#[ORM\Entity(repositoryClass: CharacterRepository::class)]
#[ORM\Table(name: 'larp_character')] // "character" is a reserved word in MySQL
#[ORM\UniqueConstraint(fields: ['edition', 'slug'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['edition', 'slug'], errorPath: 'slug')]
class Character implements SluggableInterface, \Stringable
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'characters')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?LarpEdition $edition = null;

    /**
     * The vacancy: the unique name of the role in its edition, e.g. "Inżynier".
     * It gives the address (slug) and is matched on CSV import.
     */
    #[ORM\Column(length: 160)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 160)]
    private string $name = '';

    /**
     * The character's in-game name, which may depend on who plays it, e.g.
     * "Emily Jones / Eddy Jones". Optional.
     */
    #[ORM\Column(length: 160, nullable: true)]
    #[Assert\Length(max: 160)]
    private ?string $characterName = null;

    #[ORM\Column(length: 180)]
    #[Assert\Regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', message: 'slug.invalid')]
    private ?string $slug = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Length(max: 500)]
    private ?string $teaser = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /** File name inside public/uploads/characters. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $portrait = null;

    #[ORM\Column(length: 20, enumType: CharacterCasting::class)]
    private CharacterCasting $casting = CharacterCasting::Any;

    #[ORM\Column(length: 20, enumType: CharacterAvailability::class)]
    private CharacterAvailability $availability = CharacterAvailability::Available;

    #[ORM\Column]
    private int $position = 0;

    /** @var Collection<int, CharacterTag> */
    #[ORM\OneToMany(targetEntity: CharacterTag::class, mappedBy: 'character', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['percentage' => 'DESC'])]
    #[Assert\Valid]
    private Collection $characterTags;

    public function __construct()
    {
        $this->characterTags = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->name;
    }

    #[Assert\Callback]
    public function validateUniqueTags(ExecutionContextInterface $context): void
    {
        $seen = [];
        foreach ([TagType::Trigger->value => 'triggerTags', TagType::Gameplay->value => 'gameplayTags'] as $type => $path) {
            foreach ($this->getTagsOfType(TagType::from($type)) as $characterTag) {
                $tag = $characterTag->getTag();
                if (null === $tag) {
                    continue;
                }
                if (!$tag->isAvailableFor($this->getLarp())) {
                    $context->buildViolation('character.tag_wrong_larp')
                        ->setParameter('%tag%', $tag->getName())
                        ->atPath($path)
                        ->addViolation();
                }
                $key = spl_object_id($tag);
                if (isset($seen[$key])) {
                    $context->buildViolation('character.duplicate_tag')
                        ->setParameter('%tag%', $tag->getName())
                        ->atPath($path)
                        ->addViolation();
                }
                $seen[$key] = true;
            }
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEdition(): ?LarpEdition
    {
        return $this->edition;
    }

    public function setEdition(?LarpEdition $edition): static
    {
        $this->edition = $edition;

        return $this;
    }

    public function getLarp(): ?Larp
    {
        return $this->edition?->getLarp();
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

    public function getCharacterName(): ?string
    {
        return $this->characterName;
    }

    public function setCharacterName(?string $characterName): static
    {
        $this->characterName = null === $characterName || '' === trim($characterName) ? null : trim($characterName);

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

    public function getPortrait(): ?string
    {
        return $this->portrait;
    }

    public function setPortrait(?string $portrait): static
    {
        $this->portrait = $portrait;

        return $this;
    }

    public function getCasting(): CharacterCasting
    {
        return $this->casting;
    }

    public function setCasting(CharacterCasting $casting): static
    {
        $this->casting = $casting;

        return $this;
    }

    public function getAvailability(): CharacterAvailability
    {
        return $this->availability;
    }

    public function setAvailability(CharacterAvailability $availability): static
    {
        $this->availability = $availability;

        return $this;
    }

    public function isTaken(): bool
    {
        return CharacterAvailability::Taken === $this->availability;
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

    /** @return Collection<int, CharacterTag> */
    public function getCharacterTags(): Collection
    {
        return $this->characterTags;
    }

    public function addCharacterTag(CharacterTag $characterTag): static
    {
        if (!$this->characterTags->contains($characterTag)) {
            $this->characterTags->add($characterTag);
            $characterTag->setCharacter($this);
        }

        return $this;
    }

    public function removeCharacterTag(CharacterTag $characterTag): static
    {
        $this->characterTags->removeElement($characterTag);

        return $this;
    }

    /** Shortcut for fixtures and tests. */
    public function tag(Tag $tag, int $percentage): static
    {
        return $this->addCharacterTag(new CharacterTag($tag, $percentage));
    }

    /** @return list<CharacterTag> sorted by percentage, highest first */
    public function getTagsOfType(TagType $type): array
    {
        $tags = array_values($this->characterTags->filter(
            static fn (CharacterTag $ct): bool => $ct->getTag()?->getType() === $type,
        )->toArray());
        usort($tags, static fn (CharacterTag $a, CharacterTag $b): int => $b->getPercentage() <=> $a->getPercentage());

        return $tags;
    }

    /** @return list<CharacterTag> */
    public function getTriggerTags(): array
    {
        return $this->getTagsOfType(TagType::Trigger);
    }

    public function addTriggerTag(CharacterTag $characterTag): static
    {
        return $this->addCharacterTag($characterTag);
    }

    public function removeTriggerTag(CharacterTag $characterTag): static
    {
        return $this->removeCharacterTag($characterTag);
    }

    /** @return list<CharacterTag> */
    public function getGameplayTags(): array
    {
        return $this->getTagsOfType(TagType::Gameplay);
    }

    public function addGameplayTag(CharacterTag $characterTag): static
    {
        return $this->addCharacterTag($characterTag);
    }

    public function removeGameplayTag(CharacterTag $characterTag): static
    {
        return $this->removeCharacterTag($characterTag);
    }

    public function getGameplayPercentageTotal(): int
    {
        return array_sum(array_map(static fn (CharacterTag $ct): int => $ct->getPercentage(), $this->getGameplayTags()));
    }
}
