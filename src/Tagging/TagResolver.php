<?php

declare(strict_types=1);

namespace App\Tagging;

use App\Entity\Larp;
use App\Entity\Tag;
use App\Entity\TagCategory;
use App\Enum\TagType;
use App\Repository\TagCategoryRepository;
use App\Repository\TagRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Finds tags by name for a LARP (shared tags first, then the LARP's own) and creates
 * missing ones as the LARP's own tags. Names are compared by slug, so "Śmierć",
 * "śmierć" and "Smierc" are the same tag.
 *
 * Used by the "LARP tags" admin screen (paste a list) and by the CSV import.
 */
final class TagResolver implements ResetInterface
{
    public const MAX_NAME_LENGTH = 120;

    /** @var array<string, Tag> per-request cache: "larpId|type|slug" => tag (incl. ones created but not flushed) */
    private array $cache = [];

    /** @var array<string, TagCategory> */
    private array $categories = [];

    public function __construct(
        private readonly TagRepository $tags,
        private readonly TagCategoryRepository $categoryRepository,
        private readonly EntityManagerInterface $em,
        private readonly SluggerInterface $slugger,
    ) {
    }

    public function slug(string $name): string
    {
        return $this->slugger->slug(trim($name))->lower()->toString();
    }

    public function find(Larp $larp, TagType $type, string $name): ?Tag
    {
        $slug = $this->slug($name);
        if ('' === $slug) {
            return null;
        }
        $key = $larp->getId().'|'.$type->value.'|'.$slug;
        // Only trust cached tags Doctrine still manages (the entity manager may have been cleared).
        if (isset($this->cache[$key]) && $this->em->contains($this->cache[$key])) {
            return $this->cache[$key];
        }

        $tag = $this->tags->findOneBy(['type' => $type, 'slug' => $slug, 'larp' => null])
            ?? (null !== $larp->getId() ? $this->tags->findOneBy(['type' => $type, 'slug' => $slug, 'larp' => $larp]) : null);

        return null === $tag ? null : $this->cache[$key] = $tag;
    }

    /**
     * Returns the existing tag, or creates (persists, doesn't flush) a new one owned by the LARP.
     *
     * @return array{0: Tag, 1: bool} the tag and whether it was created
     */
    public function findOrCreate(Larp $larp, TagType $type, string $name, ?string $categoryName = null): array
    {
        $existing = $this->find($larp, $type, $name);
        if (null !== $existing) {
            return [$existing, false];
        }

        $tag = (new Tag())
            ->setName(trim($name))
            ->setSlug($this->slug($name))
            ->setType($type)
            ->setLarp($larp)
            ->setCategory(null === $categoryName || '' === trim($categoryName) ? null : $this->category($type, $categoryName));
        $this->em->persist($tag);
        $this->cache[$larp->getId().'|'.$type->value.'|'.$tag->getSlug()] = $tag;

        return [$tag, true];
    }

    /**
     * Adds tags from a pasted list: one per line, "Tag" or "Category: Tag".
     * Blank lines and duplicates are ignored. Doesn't flush.
     *
     * @return array{created: list<string>, existing: list<string>, skipped: list<string>}
     */
    public function addList(Larp $larp, TagType $type, string $text): array
    {
        $result = ['created' => [], 'existing' => [], 'skipped' => []];
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = trim($line, " \t-•*");
            if ('' === $line) {
                continue;
            }
            [$category, $name] = str_contains($line, ':') ? array_map('trim', explode(':', $line, 2)) : [null, $line];
            if ('' === $name) {
                continue;
            }
            if (mb_strlen($name) > self::MAX_NAME_LENGTH || '' === $this->slug($name)) {
                $result['skipped'][] = $name;
                continue;
            }
            [$tag, $created] = $this->findOrCreate($larp, $type, $name, $category);
            $bucket = $created ? 'created' : 'existing';
            if (!\in_array($tag->getName(), $result[$bucket], true)) {
                $result[$bucket][] = $tag->getName();
            }
        }

        return $result;
    }

    /** Forget cached tags between requests (long-running servers, tests). */
    public function reset(): void
    {
        $this->cache = [];
        $this->categories = [];
    }

    /** A shared category of this type with this name, created if missing. */
    private function category(TagType $type, string $name): TagCategory
    {
        $key = $type->value.'|'.mb_strtolower(trim($name));
        if (isset($this->categories[$key]) && $this->em->contains($this->categories[$key])) {
            return $this->categories[$key];
        }

        foreach ($this->categoryRepository->findBy(['type' => $type]) as $category) {
            if (mb_strtolower($category->getName()) === mb_strtolower(trim($name))) {
                return $this->categories[$key] = $category;
            }
        }

        $category = (new TagCategory())->setName(trim($name))->setType($type)->setPosition(100);
        $this->em->persist($category);

        return $this->categories[$key] = $category;
    }
}
