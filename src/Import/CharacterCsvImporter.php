<?php

declare(strict_types=1);

namespace App\Import;

use App\Entity\Character;
use App\Entity\CharacterTag;
use App\Entity\LarpEdition;
use App\Enum\CharacterAvailability;
use App\Enum\CharacterCasting;
use App\Enum\TagType;
use App\Tagging\TagResolver;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Imports characters into one LARP edition from a CSV file, in two steps:
 *
 *   $plan = $importer->prepare($csv, $edition, updateExisting: true, createTags: true);
 *   // show $plan as a preview; if !$plan->hasErrors():
 *   $importer->apply($plan);
 *
 * prepare() never writes to the database. apply() saves everything in one
 * transaction, or nothing.
 *
 * Tag cells list "Name percentage" items separated by ";", "," or "|", e.g.
 * "Śmierć 40%; Zdrada:60, Wojna" (no number = 50%). Tags are matched by name
 * among the shared tags and the LARP's own; unknown names become the LARP's own
 * tags if createTags is on, otherwise the row is an error.
 */
final class CharacterCsvImporter
{
    public const DEFAULT_PERCENTAGE = 50;

    private const CASTING = [
        CharacterCasting::Any->value => ['', 'any', 'dowolna', 'dowolny', 'dowolne', 'kazda', 'x', '-'],
        CharacterCasting::Female->value => ['female', 'kobieca', 'kobieta', 'k', 'f', 'w', 'woman'],
        CharacterCasting::Male->value => ['male', 'meska', 'mezczyzna', 'm', 'man'],
        CharacterCasting::NonBinary->value => ['non-binary', 'non_binary', 'nonbinary', 'niebinarna', 'niebinarny', 'niebinarne', 'nb', 'enby'],
    ];

    private const AVAILABILITY = [
        CharacterAvailability::Available->value => ['', 'available', 'dostepna', 'wolna', 'tak'],
        CharacterAvailability::Taken->value => ['taken', 'zajeta', 'zajety', 'obsadzona'],
        CharacterAvailability::Hidden->value => ['hidden', 'ukryta', 'ukryty', 'niewidoczna'],
    ];

    public function __construct(
        private readonly CsvReader $reader,
        private readonly TagResolver $tags,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @param array<int, string>|null $mapping file column index => internal column ("" = ignore); null = guess from headers
     *
     * @throws CsvImportException when the file as a whole can't be read
     */
    public function prepare(string $csv, LarpEdition $edition, bool $updateExisting = true, bool $createTags = true, ?array $mapping = null, bool $hasHeader = true): ImportPlan
    {
        $table = $this->reader->read($csv, $mapping, $hasHeader);
        if (null === $edition->getLarp()) {
            throw new \LogicException('Edition without LARP.');
        }

        $existing = [];
        foreach ($edition->getCharacters() as $character) {
            $existing[(string) $character->getSlug()] = $character;
        }

        $rows = [];
        $seenSlugs = [];
        foreach ($table->rows as ['line' => $line, 'cells' => $cells]) {
            $row = new ImportRow($line, (string) $table->value($cells, 'name'));

            // More cells than headers almost always means an unquoted delimiter inside a
            // cell (e.g. "Tag 50%; Tag 50%" in a ";" file), which shifts the columns.
            $extra = $table->headerCount > 0 ? \array_slice($cells, $table->headerCount) : [];
            if ($table->headerCount > 0 && [] !== array_filter($extra, static fn (string $c): bool => '' !== trim($c))) {
                $row->error('import.row.too_many_columns', ['%delimiter%' => "\t" === $table->delimiter ? 'TAB' : $table->delimiter]);
            }

            if ('' === $row->name) {
                $row->error('import.row.name_missing');
            } elseif (mb_strlen($row->name) > 160) {
                $row->error('import.row.too_long', ['%field%' => 'nazwa', '%max%' => 160]);
            }

            $slug = $table->value($cells, 'slug');
            $row->slug = $this->tags->slug('' !== (string) $slug ? (string) $slug : $row->name);
            if ('' !== $row->slug) {
                if (isset($seenSlugs[$row->slug])) {
                    $row->error('import.row.duplicate', ['%line%' => $seenSlugs[$row->slug]]);
                }
                $seenSlugs[$row->slug] ??= $line;
            }

            $row->teaser = $table->value($cells, 'teaser');
            if (null !== $row->teaser && mb_strlen($row->teaser) > 500) {
                $row->error('import.row.too_long', ['%field%' => 'zajawka', '%max%' => 500]);
            }
            $description = $table->value($cells, 'description');
            $row->description = null === $description ? null : $this->toHtml($description);

            if ($table->has('casting')) {
                $row->casting = $this->choice(self::CASTING, (string) $table->value($cells, 'casting'), CharacterCasting::class);
                if (null === $row->casting) {
                    $row->error('import.row.bad_value', ['%field%' => 'obsada', '%value%' => (string) $table->value($cells, 'casting')]);
                }
            }
            if ($table->has('availability')) {
                $row->availability = $this->choice(self::AVAILABILITY, (string) $table->value($cells, 'availability'), CharacterAvailability::class);
                if (null === $row->availability) {
                    $row->error('import.row.bad_value', ['%field%' => 'dostępność', '%value%' => (string) $table->value($cells, 'availability')]);
                }
            }
            $position = $table->value($cells, 'position');
            if (null !== $position && '' !== $position) {
                if (1 === preg_match('/^-?\d+$/', $position)) {
                    $row->position = (int) $position;
                } else {
                    $row->error('import.row.bad_value', ['%field%' => 'kolejność', '%value%' => $position]);
                }
            }

            foreach (['gameplay' => TagType::Gameplay, 'triggers' => TagType::Trigger] as $column => $type) {
                if ($table->has($column)) {
                    $this->parseTags($row, $type, (string) $table->value($cells, $column), $edition, $createTags);
                }
            }

            $row->existing = $existing[$row->slug] ?? null;
            if (!$row->hasErrors()) {
                $row->action = null === $row->existing ? ImportRow::CREATE : ($updateExisting ? ImportRow::UPDATE : ImportRow::SKIP);
            }
            $rows[] = $row;
        }

        return new ImportPlan($edition, $table, $rows, $updateExisting, $createTags);
    }

    /**
     * Saves a plan without errors. Returns the number of created and updated characters.
     *
     * @return array{created: int, updated: int, tags: int}
     */
    public function apply(ImportPlan $plan): array
    {
        if ($plan->hasErrors()) {
            throw new \LogicException('A plan with errors cannot be applied.');
        }
        $larp = $plan->edition->getLarp() ?? throw new \LogicException('Edition without LARP.');

        return $this->em->wrapInTransaction(function () use ($plan, $larp): array {
            $result = ['created' => 0, 'updated' => 0, 'tags' => 0];
            $nextPosition = \count($plan->edition->getCharacters());

            foreach ($plan->rows as $row) {
                if (ImportRow::CREATE === $row->action) {
                    $character = (new Character())->setEdition($plan->edition)->setSlug($row->slug)->setPosition($nextPosition++);
                    $plan->edition->getCharacters()->add($character);
                    $this->em->persist($character);
                    ++$result['created'];
                } elseif (ImportRow::UPDATE === $row->action && null !== $row->existing) {
                    $character = $row->existing;
                    ++$result['updated'];
                } else {
                    continue;
                }

                $character->setName($row->name);
                if (null !== $row->teaser) {
                    $character->setTeaser('' === $row->teaser ? null : $row->teaser);
                }
                if (null !== $row->description) {
                    $character->setDescription('' === $row->description ? null : $row->description);
                }
                if (null !== $row->casting) {
                    $character->setCasting($row->casting);
                }
                if (null !== $row->availability) {
                    $character->setAvailability($row->availability);
                }
                if (null !== $row->position) {
                    $character->setPosition($row->position);
                }

                foreach ($row->tags as $typeValue => $items) {
                    $type = TagType::from($typeValue);
                    $wanted = [];
                    foreach ($items as $item) {
                        [$tag, $created] = $this->tags->findOrCreate($larp, $type, $item['name']);
                        $result['tags'] += $created ? 1 : 0;
                        $wanted[spl_object_id($tag)] = [$tag, $item['percentage']];
                    }
                    // Keep existing rows for tags that stay (a delete + insert of the same
                    // tag in one flush would hit the unique index), update their %, drop the rest.
                    foreach ($character->getTagsOfType($type) as $characterTag) {
                        $id = spl_object_id($characterTag->getTag() ?? $characterTag);
                        if (isset($wanted[$id])) {
                            $characterTag->setPercentage($wanted[$id][1]);
                            unset($wanted[$id]);
                        } else {
                            $character->removeCharacterTag($characterTag);
                        }
                    }
                    foreach ($wanted as [$tag, $percentage]) {
                        $character->addCharacterTag(new CharacterTag($tag, $percentage));
                    }
                }
            }

            $this->em->flush();

            return $result;
        });
    }

    private function parseTags(ImportRow $row, TagType $type, string $cell, LarpEdition $edition, bool $createTags): void
    {
        $larp = $edition->getLarp();
        $items = [];
        foreach (preg_split('/[;,|\n]/u', $cell) ?: [] as $raw) {
            $raw = trim($raw);
            if ('' === $raw) {
                continue;
            }
            $name = $raw;
            $percentage = self::DEFAULT_PERCENTAGE;
            if (1 === preg_match('/^(.+?)\s*(?:[:=]\s*|\s+)(\d{1,3})\s*%?$/u', $raw, $m)) {
                $name = trim($m[1]);
                $percentage = (int) $m[2];
            }
            if ($percentage < 1 || $percentage > 100) {
                $row->error('import.row.bad_percentage', ['%tag%' => $name, '%value%' => $percentage]);
                continue;
            }
            if (mb_strlen($name) > TagResolver::MAX_NAME_LENGTH) {
                $row->error('import.row.too_long', ['%field%' => $name, '%max%' => TagResolver::MAX_NAME_LENGTH]);
                continue;
            }
            $key = $this->tags->slug($name);
            if (isset($items[$key])) {
                $row->error('import.row.duplicate_tag', ['%tag%' => $name]);
                continue;
            }

            $tag = null !== $larp ? $this->tags->find($larp, $type, $name) : null;
            $otherType = TagType::Trigger === $type ? TagType::Gameplay : TagType::Trigger;
            if (null === $tag && null !== $larp && null !== $this->tags->find($larp, $otherType, $name)) {
                // E.g. "Komedia" in the triggers column: most likely shifted columns.
                $row->error('import.row.wrong_column', ['%tag%' => $name]);
                continue;
            }
            if (null === $tag) {
                if ($createTags) {
                    $row->newTags[] = $type->value.'|'.$name;
                } else {
                    $row->error('import.row.unknown_tag', ['%tag%' => $name]);
                    continue;
                }
            }
            $items[$key] = ['name' => $tag?->getName() ?? $name, 'percentage' => $percentage];
        }
        $row->tags[$type->value] = array_values($items);
    }

    /**
     * @template T of CharacterCasting|CharacterAvailability
     *
     * @param array<string, list<string>> $map
     * @param class-string<T>             $enum
     *
     * @return T|null
     */
    private function choice(array $map, string $value, string $enum): CharacterCasting|CharacterAvailability|null
    {
        $key = $this->tags->slug($value);
        foreach ($map as $enumValue => $aliases) {
            if (\in_array($key, $aliases, true) || \in_array(str_replace('-', '_', $key), $aliases, true)) {
                return $enum::from($enumValue);
            }
        }

        return null;
    }

    /** Plain text from a spreadsheet becomes paragraphs; HTML is kept as it is. */
    private function toHtml(string $text): string
    {
        if ('' === $text || $text !== strip_tags($text)) {
            return $text;
        }
        $paragraphs = preg_split('/\n\s*\n/u', $text) ?: [];

        return implode('', array_map(
            static fn (string $p): string => '<p>'.nl2br(htmlspecialchars(trim($p), \ENT_QUOTES)).'</p>',
            array_filter($paragraphs, static fn (string $p): bool => '' !== trim($p)),
        ));
    }
}
