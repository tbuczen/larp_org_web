<?php

declare(strict_types=1);

namespace App\Import;

/**
 * A parsed CSV file: known columns (by internal name) and the data rows.
 */
final readonly class CsvTable
{
    /**
     * @param array<string, int>                          $columns        internal column name => index
     * @param list<array{line: int, cells: list<string>}> $rows
     * @param list<string>                                $ignoredHeaders headers that matched no known column
     */
    public function __construct(
        public array $columns,
        public array $rows,
        public string $delimiter,
        public string $encoding,
        public array $ignoredHeaders = [],
        public int $headerCount = 0,
    ) {
    }

    public function has(string $column): bool
    {
        return isset($this->columns[$column]);
    }

    /** @param list<string> $cells */
    public function value(array $cells, string $column): ?string
    {
        if (!isset($this->columns[$column])) {
            return null;
        }

        return trim($cells[$this->columns[$column]] ?? '');
    }
}
