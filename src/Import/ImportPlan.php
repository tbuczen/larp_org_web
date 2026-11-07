<?php

declare(strict_types=1);

namespace App\Import;

use App\Entity\LarpEdition;

/**
 * The checked contents of a CSV file: shown as a preview, then applied.
 */
final readonly class ImportPlan
{
    /** @param list<ImportRow> $rows */
    public function __construct(
        public LarpEdition $edition,
        public CsvTable $table,
        public array $rows,
        public bool $updateExisting,
        public bool $createTags,
    ) {
    }

    public function count(string $action): int
    {
        return \count(array_filter($this->rows, static fn (ImportRow $r): bool => $r->action === $action));
    }

    public function hasErrors(): bool
    {
        return $this->count(ImportRow::ERROR) > 0;
    }

    /** @return array<string, list<string>> tag type value => new tag names */
    public function newTags(): array
    {
        $tags = [];
        foreach ($this->rows as $row) {
            foreach ($row->newTags as $entry) {
                [$type, $name] = explode('|', $entry, 2);
                $tags[$type][mb_strtolower($name)] = $name;
            }
        }

        return array_map(static fn (array $names): array => array_values($names), $tags);
    }
}
